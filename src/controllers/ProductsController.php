<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\controllers;

use Craft;
use craft\helpers\App;
use craft\helpers\UrlHelper;
use craft\shopify\elements\Product;
use craft\shopify\Plugin;
use yii\web\Response;

/**
 * The ProductsController handles listing and showing Shopify products elements.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 */
class ProductsController extends \craft\web\Controller
{
    /**
     * Displays the product index page.
     *
     * @return Response
     */
    public function actionProductIndex(): Response
    {
        $newProductUrl = '';
        if ($baseUrl = Plugin::getInstance()->getSettings()->getHostName(true)) {
            $newProductUrl = UrlHelper::url('https://' . App::parseEnv($baseUrl) . '/admin/products/new');
        }

        return $this->renderTemplate('shopify/products/_index', compact('newProductUrl'));
    }

    /**
     * Syncs all products
     *
     * @return Response|null
     */
    public function actionSync(): ?Response
    {
        // Users must have access to the utility to manage synchronizations:
        $this->requirePermission('utility:shopify-sync');

        $result = Plugin::getInstance()->getBulkOperations()->createProductsBulkOperation();

        if ($result === false) {
            return $this->asFailure(Craft::t('shopify', 'Failed to create products sync'));
        }

        return $this->asSuccess(Craft::t('shopify', 'Products sync created'));
    }
}
