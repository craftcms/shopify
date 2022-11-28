<?php

namespace craft\shopify\services;

use craft\base\Component;
use craft\commerce\events\EmailEvent;
use craft\commerce\models\Email;
use craft\commerce\records\Email as EmailRecord;
use craft\commerce\services\Pdfs;
use craft\db\Query;
use craft\events\ConfigEvent;
use craft\helpers\App;
use craft\helpers\Db;
use craft\helpers\StringHelper;
use craft\helpers\UrlHelper;
use craft\shopify\db\Table;
use craft\shopify\events\StoreEvent;
use craft\shopify\models\Store;
use craft\shopify\Plugin;
use Illuminate\Support\Collection;
use yii\base\InvalidConfigException;

/**
 * Shopify Store service.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 */
class Stores extends Component
{
    public const EVENT_BEFORE_SAVE_STORE = 'beforeSaveStore';

    public const CONFIG_STORES_KEY = 'shopify.stores';

    /**
     * @return Collection<Store>
     */
    public function getAllStores(): Collection
    {

    }

    /**
     * @param Store $store
     * @param bool $runValidation
     * @return void
     */
    public function saveStore(Store $store, bool $runValidation = true): bool
    {
        $isNewStore = !(bool)$store->id;

        // Fire a 'beforeSaveStore' event
        if ($this->hasEventHandlers(self::EVENT_BEFORE_SAVE_STORE)) {
            $this->trigger(self::EVENT_BEFORE_SAVE_STORE, new StoreEvent([
                'store' => $store,
                'isNew' => $isNewStore,
            ]));
        }

        if ($runValidation && !$store->validate()) {
            Craft::info('Store not saved due to validation error(s).', __METHOD__);
            return false;
        }

        if ($isNewStore) {
            $store->uid = StringHelper::UUID();
        }

        $configPath = self::CONFIG_STORES_KEY . '.' . $store->uid;
        $configData = $store->getConfig();
        Craft::$app->getProjectConfig()->set($configPath, $configData);

        if ($isNewStore) {
            $store->id = Db::idByUid(Table::STORES, $store->uid);
        }

        return true;

    }

    /**
     * Handle stores status change.
     *
     * @throws Throwable if reasons
     */
    public function handleChangedStore(ConfigEvent $event): void
    {
        $emailUid = $event->tokenMatches[0];
        $data = $event->newValue;

        $pdfUid = $data['pdf'] ?? null;
        if ($pdfUid) {
            Craft::$app->getProjectConfig()->processConfigChanges(Pdfs::CONFIG_PDFS_KEY . '.' . $pdfUid);
        }

        $transaction = Craft::$app->getDb()->beginTransaction();
        try {
            $emailRecord = $this->_getEmailRecord($emailUid);
            $isNewEmail = $emailRecord->getIsNewRecord();

            $emailRecord->name = $data['name'];
            $emailRecord->subject = $data['subject'];
            $emailRecord->recipientType = $data['recipientType'];
            $emailRecord->to = $data['to'];
            $emailRecord->bcc = $data['bcc'];
            $emailRecord->cc = $data['cc'] ?? null;
            $emailRecord->replyTo = $data['replyTo'] ?? null;
            $emailRecord->enabled = $data['enabled'];
            $emailRecord->templatePath = $data['templatePath'];
            $emailRecord->plainTextTemplatePath = $data['plainTextTemplatePath'] ?? null;
            $emailRecord->uid = $emailUid;
            $emailRecord->pdfId = $pdfUid ? Db::idByUid(\craft\commerce\db\Table::PDFS, $pdfUid) : null;
            $emailRecord->language = $data['language'] ?? EmailRecord::LOCALE_ORDER_LANGUAGE;

            $emailRecord->save(false);

            $transaction->commit();
        } catch (Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        // Fire a 'afterSaveEmail' event
        if ($this->hasEventHandlers(self::EVENT_AFTER_SAVE_EMAIL)) {
            $this->trigger(self::EVENT_AFTER_SAVE_EMAIL, new EmailEvent([
                'email' => $this->getEmailById($emailRecord->id),
                'isNew' => $isNewEmail,
            ]));
        }
    }

    private function _createStoresQuery()
    {
        return (new Query())
            ->select([
                'stores.id',
                'stores.name',
                'stores.hostName',
                'stores.apiKey',
                'stores.apiSecretKey',
                'stores.accessToken',
                'stores.uriFormat',
                'stores.template',
            ])
            ->from(['stores' => Table::STORES])
            ->orderBy(['name' => SORT_ASC]);
    }
}
