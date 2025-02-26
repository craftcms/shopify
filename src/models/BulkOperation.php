<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\models;

use craft\base\Model;
use craft\shopify\enums\BulkOperationStatus;
use craft\shopify\records\BulkOperation as BulkOperationRecord;
use DateTime;

/**
 * Bulk Operations model.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 6.0.0
 */
class BulkOperation extends Model
{
    /**
     * @var int|null
     */
    public ?int $id = null;

    /**
     * @var string|null The Shopify ID of the bulk operation.
     */
    public ?string $shopifyId = null;

    /**
     * @var string|null The URL of the bulk operation data.
     */
    public ?string $url = null;

    /**
     * @var int|null
     */
    public ?int $objectCount = null;

    /**
     * @var string
     * @see getStatus()
     * @see setStatus()
     */
    private string $_status = BulkOperationStatus::Queued->value;

    /**
     * @var string|null
     */
    public ?string $shopifyStatus = null;

    /**
     * @var string|null
     */
    public ?string $query = null;

    /**
     * Signal which data should be cleared before processing the bulk operation.
     * This is either `none`, `all` or a gid string.
     *
     * @var string
     */
    public string $clearData = BulkOperationRecord::CLEAR_DATA_NONE;

    /**
     * @var DateTime|null
     */
    public ?DateTime $dateCreated = null;

    /**
     * @var DateTime|null
     */
    public ?DateTime $dateUpdated = null;

    protected function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [['id', 'objectCount'], 'number', 'integerOnly' => true];
        $rules[] = [['shopifyId', 'url'], 'string'];
        $rules[] = [['id', 'shopifyId', 'url', 'objectCount', 'status', 'shopifyStatus', 'query', 'dateCreated', 'dateUpdated'], 'safe'];

        return $rules;
    }

    /**
     * @inheritdoc
     */
    public function attributes()
    {
        $names = parent::attributes();
        $names[] = 'status';

        return $names;
    }

    /**
     * @param BulkOperationStatus|string $status
     * @return void
     */
    public function setStatus(BulkOperationStatus|string $status): void
    {
        $this->_status = $status instanceof BulkOperationStatus ? $status->value : $status;
    }

    /**
     * @return BulkOperationStatus|null
     */
    public function getStatus(): ?BulkOperationStatus
    {
        return BulkOperationStatus::tryFrom($this->_status);
    }

    /**
     * @return string
     */
    public function statusLabelHtml(): string
    {
        return $this->getStatus()?->statusLabelHtml() ?? '';
    }
}
