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
    public $defaultAction = 'all';

    /**
     * Sync all Shopify data.
     */
    public function actionAll()
    {
        $this->_syncProducts();
        return ExitCode::OK;
    }

    /**
     * Sync Products only.
     */
    public function actionProducts(): int
    {
        $this->_syncProducts();
        return ExitCode::OK;
    }

    private function _syncProducts(): void
    {
        $this->stdout('Syncing Shopify products…' . PHP_EOL . PHP_EOL, Console::FG_GREEN);

        $result = Plugin::getInstance()->getBulkOperations()->createProductsBulkOperation();

        if ($result === false) {
            $this->stderr('Failed to create a bulk operation.' . PHP_EOL, Console::FG_RED);
            return;
        }

        $this->stdout('Shopify products sync requested ' . PHP_EOL . PHP_EOL, Console::FG_GREEN);
    }
}
