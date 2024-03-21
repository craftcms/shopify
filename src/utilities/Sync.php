<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\utilities;

use Craft;
use craft\base\Utility;

/**
 * Sync class offers the Shopify Sync utilities.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0.0
 */
class Sync extends Utility
{
    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('app', 'Shopify Sync');
    }

    /**
     * @inheritdoc
     */
    public static function id(): string
    {
        return 'shopify-sync';
    }

    /**
     * @inheritdoc
     */
    public static function icon(): ?string
    {
        return Craft::getAlias('@vendor') . '/craftcms/shopify/src/icon-mask.svg';
    }

    /**
     * @inheritdoc
     */
    public static function contentHtml(): string
    {
        $view = Craft::$app->getView();

        return $view->renderTemplate('shopify/utilities/_sync.twig');
    }
}
