<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\console\controllers;

use Craft;
use craft\console\Controller;
use craft\helpers\Console;
use craft\helpers\Db;
use craft\shopify\elements\Product;
use Exception;
use yii\console\ExitCode;

/**
 * Data controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 6.0.0
 */
class DataController extends Controller
{
    public $defaultAction = 'reset';

    /**
     * Deletes all Shopify plugin data.
     *
     * @return int
     * @throws \Throwable
     */
    public function actionReset(): int
    {
        $this->stdout('Resetting Shopify plugin data will permanently delete all:' . PHP_EOL);
        $this->stdout('  > products' . PHP_EOL);
        $this->stdout('  > variants' . PHP_EOL);
        $this->stdout('from Craft. (Data in Shopify will not be affected.)' . PHP_EOL . PHP_EOL);

        if (!$this->confirm(
            'Do you wish to continue?'
        )) {
            return ExitCode::OK;
        }

        $transaction = Craft::$app->getDb()->beginTransaction();

        try {
            $this->_deleteProducts();

            $this->stdout(PHP_EOL . 'Finished.' . PHP_EOL . PHP_EOL, Console::FG_GREEN);

            $transaction->commit();
        } catch (Exception $e) {
            $this->stdout($e->getmessage() . PHP_EOL, Console::FG_RED);
            $transaction->rollBack();
        }

        return ExitCode::OK;
    }

    /**
     * Deletes product elements and contents of the corresponding data tables
     *
     * @return void
     * @throws \Throwable
     */
    private function _deleteProducts(): void
    {
        $elementsService = Craft::$app->getElements();

        $productQuery = Product::find()
            ->status(null)
            ->unique();

        $this->stdout('  > Removing Products ...');
        foreach (Db::each($productQuery) as $product) {
            $elementsService->deleteElement($product, true);
        }

        $this->stdout(' done' . PHP_EOL, Console::FG_GREEN);
    }
}
