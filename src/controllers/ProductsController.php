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
use craft\shopify\helpers\Product as ProductHelper;
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
        if ($baseUrl = Plugin::getInstance()->getSettings()->hostName) {
            $newProductUrl = UrlHelper::url('https://' . App::parseEnv($baseUrl) . '/admin/products/new');
        }

        return $this->renderTemplate('shopify/products/_index', compact('newProductUrl'));
    }

    /**
     * Syncs all products
     *
     * @return Response
     */
    public function actionSync(): Response
    {
        Plugin::getInstance()->getProducts()->syncAllProducts();
        return $this->asSuccess(Craft::t('shopify','Products successfully synced'));
    }

    /**
     * Renders the card HTML.
     *
     * @return string
     */
    public function actionRenderCardHtml(): string
    {
        $id = (int)Craft::$app->request->getParam('id');
        /** @var Product $product */
        $product = Product::find()->id($id)->status(null)->one();
        return ProductHelper::renderCardHtml($product);
    }
}
