<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\controllers;

use Craft;
use craft\shopify\elements\Product;
use craft\shopify\helpers\Product as ProductHelper;
use craft\shopify\Plugin;
use craft\shopify\records\ProductData;
use yii\web\Response;

/**
 * The ProductsController handles listing and showing Shopify products elments.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 1.0.0
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
        return $this->renderTemplate('shopify/products/_index');
    }

    /**
     * Syncs all products
     *
     */
    public function actionSync(): Response
    {
        Plugin::getInstance()->getProducts()->syncAllProducts();
        return $this->asSuccess('Products started syncing successfully');
    }

    /**
     * @return string
     */
    public function actionRenderCardHtml(): string
    {
        $id = (int)Craft::$app->request->getParam('id');
        $product = Product::find()->id($id)->status(null)->one();
        return ProductHelper::renderCardHtml($product);
    }
}