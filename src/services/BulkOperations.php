<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\services;

use Craft;
use craft\base\Component;
use craft\db\Query;
use craft\helpers\DateTimeHelper;
use craft\helpers\Db;
use craft\helpers\Queue;
use craft\shopify\db\Table;
use craft\shopify\enums\BulkOperationStatus;
use craft\shopify\jobs\ProcessBulkOperationData;
use craft\shopify\models\BulkOperation;
use craft\shopify\Plugin;
use craft\shopify\records\BulkOperation as BulkOperationRecord;
use GraphQL\InlineFragment;
use GraphQL\Mutation;
use GraphQL\Variable;
use Illuminate\Support\Collection;
use yii\base\InvalidConfigException;
use yii\db\Exception;
use yii\db\StaleObjectException;

/**
 *
 * BulkOperations service.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 6.0.0
 */
class BulkOperations extends Component
{
    /**
     * @return Collection
     * @throws InvalidConfigException
     */
    public function getAllBulkOperations(): Collection
    {
        $bulkOps = $this->_createBulkOperationQuery()->all();

        foreach ($bulkOps as &$bulkOp) {
            $bulkOp = Craft::createObject(array_merge($bulkOp, ['class' => BulkOperation::class]));
        }

        return collect($bulkOps);
    }

    /**
     * @param string $shopifyId
     * @return BulkOperation|null
     * @throws InvalidConfigException
     */
    public function getBulkOperationByShopifyId(string $shopifyId): ?BulkOperation
    {
        return $this->getAllBulkOperations()->firstWhere('shopifyId', $shopifyId);
    }

    /**
     * @param string $query
     * @return bool
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function createBulkOperation(string $query, string $clearData = BulkOperationRecord::CLEAR_DATA_NONE): bool
    {
        $bulkOperation = Craft::createObject([
            'class' => BulkOperation::class,
            'status' => BulkOperationStatus::Queued,
            'query' => $query,
            'clearData' => $clearData,
        ]);

        if (!$this->saveBulkOperation($bulkOperation)) {
            Craft::error('Could not save bulk operation data.', __METHOD__);
            return false;
        }

        $this->nextBulkOperation();
        return true;
    }

    /**
     * Retrieve all a shop’s products.
     *
     * @return bool
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function createProductsBulkOperation(): bool
    {
        return $this->createBulkOperation((string)Plugin::getInstance()->getApi()->getProductGql(), BulkOperationRecord::CLEAR_DATA_ALL);
    }

    /**
     * @return bool
     * @throws InvalidConfigException
     */
    public function nextBulkOperation(): bool
    {
        // If we are processing the data of a bulk op or a bulk op query has been sent to Shopify, we can't start another one
        $hasBulkOpsInProgress = $this->_createBulkOperationQuery()
            ->andWhere([
                'status' => [BulkOperationStatus::Processing->value, BulkOperationStatus::Created->value],
            ])
            ->exists();

        if ($hasBulkOpsInProgress) {
            return false;
        }

        // Retrieve the next queued bulk operation
        $result = $this->_createBulkOperationQuery()
            ->andWhere(['status' => BulkOperationStatus::Queued->value])
            ->one();

        if (!$result) {
            // There isn't any queued bulk operations
            return true;
        }

        /** @var BulkOperation $bulkOperation */
        $bulkOperation = Craft::createObject(array_merge($result, ['class' => BulkOperation::class]));

        // Before trying to create a new bulk op in shopify, we should call their API to see if there is one running
        // This will ensure we don't cause any issue with other processes

        $bulkOpsStatusQuery = (new \GraphQL\Query('currentBulkOperation'))
            ->setSelectionSet([
                'id',
                'type',
                'status',
            ]);
        $bulkOpStatusResponse = Plugin::getInstance()->getApi()->query($bulkOpsStatusQuery);

        if ($bulkOpStatusResponse === false || ($bulkOpStatusResponse !== null && in_array($bulkOpStatusResponse['status'], ['RUNNING', 'CREATED']))) {
            return false;
        }

        $mutation = (new Mutation('bulkOperationRunQuery'))
            ->setOperationName('bulkOperationRunQuery')
            ->setVariables([new Variable('query', 'String!')])
            ->setArguments(['query' => '$query'])
            ->setSelectionSet([
                (new \GraphQL\Query('bulkOperation'))
                    ->setSelectionSet([
                        'id',
                        'status',
                        'type',
                    ]),
                (new \GraphQL\Query('userErrors'))
                    ->setSelectionSet([
                        'code',
                        'field',
                        'message',
                    ]),
            ]);

        $response = Plugin::getInstance()->getApi()->query($mutation, ['query' => $bulkOperation->query]);

        if (!$response) {
            $bulkOperation->setStatus(BulkOperationStatus::Completed);
            $this->saveBulkOperation($bulkOperation, false);
            return false;
        }

        if (isset($response['bulkOperation']['userErrors']) && !empty($response['bulkOperation']['userErrors'])) {
            Craft::error('Could not start bulk operation: ' . $response['bulkOperation']['userErrors'][0]['message'], __METHOD__);
            return false;
        }

        if ($response['bulkOperation']['status'] === 'CREATED') {
            $bulkOperation->setStatus(BulkOperationStatus::Created);
        }

        $bulkOperation->shopifyStatus = $response['bulkOperation']['status'];
        $bulkOperation->shopifyId = $response['bulkOperation']['id'];
        $this->saveBulkOperation($bulkOperation);
        return true;
    }

    /**
     * @param array $data
     * @return void
     * @since 6.0.0
     */
    public function handleBulkOperationFinished(array $data): void
    {
        // Exit out if we don't have the necessary data
        if (!isset($data['admin_graphql_api_id']) || !isset($data['status'])) {
            return;
        }

        $bulkOperation = $this->getBulkOperationByShopifyId($data['admin_graphql_api_id']);
        if (!$bulkOperation) {
            return;
        }

        // If it isn't a completed status we need to update the queue
        if ($data['status'] !== 'completed') {
            $deletableStatuses = [
                'CANCELED',
                'EXPIRED',
                'FAILED',
            ];
            if (in_array($data['status'], $deletableStatuses)) {
                $bulkOperation->setStatus(BulkOperationStatus::Completed);
            }

            $bulkOperation->shopifyStatus = $data['status'];
            $this->saveBulkOperation($bulkOperation, false);
            return;
        }

        $query = (new \GraphQL\Query('node'))
            ->setArguments(['id' => $data['admin_graphql_api_id']])
            ->setSelectionSet([
                (new InlineFragment('BulkOperation'))
                    ->setSelectionSet([
                        'status',
                        'url',
                        'partialDataUrl',
                        'objectCount',
                    ]),
            ]);

        try {
            $response = Plugin::getInstance()->getApi()->getGqlClient()->query(['query' => (string)$query]);
            $body = $response->getDecodedBody();

            if (!isset($body['data']['node'])) {
                return;
            }

            // Store the data from the `$body['data']['url'] and start the queue job to process it
            $bulkOperation->shopifyStatus = $body['data']['node']['status'];
            $bulkOperation->url = $body['data']['node']['url'];
            $bulkOperation->objectCount = $body['data']['node']['objectCount'];

            if (!$this->saveBulkOperation($bulkOperation)) {
                Craft::error('Could not save bulk operation data.', __METHOD__);
                return;
            }
        } catch (\Exception $e) {
            Craft::error('Could not get bulk operation data: ' . $e->getMessage(), __METHOD__);
        }

        $this->queueNextBulkOperation();
    }

    /**
     * @return bool
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function queueNextBulkOperation(): bool
    {
        $hasBulkOpsInProgress = $this->_createBulkOperationQuery()
            ->andWhere([
                'status' => [BulkOperationStatus::Processing->value],
            ])
            ->exists();

        if ($hasBulkOpsInProgress) {
            return false;
        }

        $nextToProcess = $this->_createBulkOperationQuery()
            ->andWhere(['status' => BulkOperationStatus::Created->value])
            ->andWhere(['not', ['url' => null]])
            ->one();

        if (!$nextToProcess) {
            return true;
        }

        /** @var BulkOperation $bulkOperation */
        $bulkOperation = Craft::createObject(array_merge($nextToProcess, ['class' => BulkOperation::class]));
        if (!Queue::push(new ProcessBulkOperationData([
            'bulkOperationShopifyId' => $bulkOperation->shopifyId,
            'dataUrl' => $bulkOperation->url,
            'objectCount' => $bulkOperation->objectCount,
            'clearData' => $bulkOperation->clearData,
        ]))) {
            return false;
        }

        $bulkOperation->setStatus(BulkOperationStatus::Processing);

        if (!$this->saveBulkOperation($bulkOperation)) {
            Craft::error('Could not save bulk operation data.', __METHOD__);
            return false;
        }

        return true;
    }

    /**
     * @param BulkOperation $bulkOperation
     * @param bool $runValidation
     * @return bool
     * @throws Exception
     */
    public function saveBulkOperation(BulkOperation $bulkOperation, bool $runValidation = true): bool
    {
        // Find record if it exists
        if ($bulkOperation->id) {
            $record = BulkOperationRecord::findOne($bulkOperation->id);
        } else {
            $record = BulkOperationRecord::findOne(['shopifyId' => $bulkOperation->shopifyId]);
        }

        if (!$record) {
            $record = new BulkOperationRecord();
        }

        if ($runValidation && !$bulkOperation->validate()) {
            Craft::info('Bulk operation not saved due to validation error.', __METHOD__);

            return false;
        }

        $record->clearData = $bulkOperation->clearData;
        $record->shopifyId = $bulkOperation->shopifyId;
        $record->url = $bulkOperation->url;
        $record->objectCount = $bulkOperation->objectCount;
        $record->query = $bulkOperation->query;
        $record->status = $bulkOperation->getStatus()?->value;
        $record->shopifyStatus = $bulkOperation->shopifyStatus;

        $record->validate();
        $bulkOperation->addErrors($record->getErrors());

        if (!$record->save(false)) {
            return false;
        }

        $bulkOperation->id = $record->id;

        return true;
    }

    /**
     * @param int $id
     * @return bool
     * @throws \Throwable
     * @throws \yii\db\StaleObjectException
     */
    public function deleteBulkOperationById(int $id): bool
    {
        $bulkOperation = BulkOperationRecord::findOne(['id' => $id]);
        if (!$bulkOperation) {
            return true;
        }

        // Cannot delete a bulk operation that is currently processing
        if ($bulkOperation->status === BulkOperationStatus::Processing->value) {
            return false;
        }

        if (!$bulkOperation->delete()) {
            return false;
        }

        return true;
    }

    /**
     * @return void
     * @throws \DateInvalidOperationException
     * @throws \Throwable
     * @throws StaleObjectException
     */
    public function purgeBulkOperations(): void
    {
        $edge = DateTimeHelper::now();
        $interval = DateTimeHelper::toDateInterval('P7D');
        $edge->sub($interval);

        // Delete all bulk operations completed and older than 7 days
        $completedBulkOps = $this->_createBulkOperationQuery()
            ->andWhere(['status' => BulkOperationStatus::Completed->value])
            ->andWhere(['<', 'dateUpdated', Db::prepareDateForDb($edge)])
            ->all();

        foreach ($completedBulkOps as $completedBulkOp) {
            $this->deleteBulkOperationById($completedBulkOp['id']);
        }
    }

    /**
     * Returns a Query object prepped for retrieving shipping methods.
     */
    private function _createBulkOperationQuery(): Query
    {
        return (new Query())
            ->select([
                'clearData',
                'dateCreated',
                'dateUpdated',
                'id',
                'objectCount',
                'query',
                'shopifyId',
                'status',
                'shopifyStatus',
                'url',
            ])
            ->from([Table::BULK_OPERATIONS])
            ->orderBy(['id' => SORT_DESC]);
    }
}
