<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\console\controllers;

use craft\console\Controller;
use craft\helpers\Console;
use craft\shopify\elements\Product;
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
     * @var bool Whether to slow down API requests to avoid rate limiting.
     * @since 5.2.0
     */
    public bool $throttle = false;

    /**
     * @inheritdoc
     */
    public function options($actionID): array
    {
        $options = parent::options($actionID);
        $options[] = 'throttle';
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
        // start timer
        $start = microtime(true);

        $originalThrottle = Plugin::getInstance()->getProducts()->throttle;
        Plugin::getInstance()->getProducts()->throttle = $this->throttle;

        Plugin::getInstance()->getProducts()->syncAllProducts();

        Plugin::getInstance()->getProducts()->throttle = $originalThrottle;

        // end timer
        $time = microtime(true) - $start;
        $this->stdout('Finished syncing ' . Product::find()->count() . ' product(s) in ' . round($time, 2) . 's' . PHP_EOL . PHP_EOL, Console::FG_GREEN);
    }
}
