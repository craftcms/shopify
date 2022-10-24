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
    public $defaultAction = 'all';

    
    public function actionAll()
    {
        $this->actionProducts();
    }
    
    /**
     * Reset Commerce data.
     */
    public function actionProducts(): int
    {
        $this->stdout('Syncing Shopify products…' . PHP_EOL . PHP_EOL, Console::FG_GREEN);

        Plugin::getInstance()->getProducts()->syncAllProducts();
        
        $this->stdout('Syncing Shopify products…' . PHP_EOL . PHP_EOL, Console::FG_GREEN);
        
        return ExitCode::OK;
    }
}
