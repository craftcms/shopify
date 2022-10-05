<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\web\twig;

use Craft;
use craft\shopify\elements\db\ProductQuery;
use craft\shopify\elements\Product;
use yii\base\Behavior;

/**
 * Class CraftVariableBehavior
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class CraftVariableBehavior extends Behavior
{
    /**
     * Returns a new ProductQuery instance.
     *
     * @param array $criteria
     * @return ProductQuery
     */
    public function shopifyProducts(array $criteria = []): ProductQuery
    {
        $query = Product::find();
        Craft::configure($query, $criteria);
        return $query;
    }
}
