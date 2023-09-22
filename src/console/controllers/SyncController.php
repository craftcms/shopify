<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\console\controllers;

use craft\console\Controller;
use craft\helpers\Console;
use craft\shopify\Plugin;
use yii\console\ExitCode;

/**
 * Allows you to sync Shopify data
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 */
class SyncController extends Controller
{
    /** @var string $defaultAction */
    public $defaultAction = 'products';

    /**
     * @var bool Whether to sync product and other associated data in the queue.
     * @since 3.3.0
     */
    public bool $async = true;

    /**
     * @inheritdoc
     */
    public function options($actionID): array
    {
        $options = parent::options($actionID);
        $options[] = 'async';
        return $options;
    }

    /**
     * Sync all Shopify data.
     */
    public function actionAll()
    {
        $this->_syncProducts();
        return ExitCode::OK;
    }

    /**
     * Reset Commerce data.
     */
    public function actionProducts(): int
    {
        $this->_syncProducts();
        return ExitCode::OK;
    }

    private function _syncProducts(): void
    {
        $this->stdout('Syncing Shopify products…' . PHP_EOL . PHP_EOL, Console::FG_GREEN);
        Plugin::getInstance()->getProducts()->syncAllProducts($this->async);
        $this->stdout('Finished' . PHP_EOL . PHP_EOL, Console::FG_GREEN);
    }
}
