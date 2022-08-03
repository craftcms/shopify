<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\elements\db;


use craft\db\QueryAbortedException;
use craft\elements\db\ElementQuery;
use craft\shopify\db\Table;
use craft\shopify\elements\Product;

class ProductQuery extends ElementQuery
{

    /**
     * @var mixed The Shopify product ID(s) that the resulting products must have.
     */
    public mixed $shopifyId = null;

    /**
     * Narrows the query results based on the Shopify product ID
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `1` | of a type with an ID of 1.
     * | `'not 1'` | not of a type with an ID of 1.
     * | `[1, 2]` | of a type with an ID of 1 or 2.
     * | `['not', 1, 2]` | not of a type with an ID of 1 or 2.
     *
     * ---
     *
     * ```twig
     * {# Fetch {elements} of the product type with an ID of 1 #}
     * {% set {elements-var} = {twig-method}
     *   .shopifyId(54321)
     *   .all() %}
     * ```
     *
     * ```php
     * // Fetch {elements} of the product type with an ID of 1
     * ${elements-var} = {php-method}
     *     ->shopifyId(54321)
     *     ->all();
     * ```
     *
     * @param mixed $value The property value
     * @return static self reference
     */
    public function shopifyId(mixed $value): ProductQuery
    {
        $this->shopifyId = $value;
        return $this;
    }

//    /**
//     * @inheritdoc
//     */
//    protected function statusCondition(string $status): mixed
//    {
//        return [
//            'shopify_productdata.status' => $status,
//        ];
//    }

    /**
     * @inheritdoc
     * @throws QueryAbortedException
     */
    protected function beforePrepare(): bool
    {
        $shopifyId = $this->shopifyId;

        if ($this->shopifyId === []) {
            return false;
        }

        $productTable = 'shopify_products';
        $productDataTable = 'shopify_productdata';

        $this->joinElementTable($productTable);

        $productDataJoinTable = [$productDataTable => "{{%$productDataTable}}"];
        $this->query->innerJoin($productDataJoinTable, "[[$productDataTable.id]] = [[shopify_products.shopifyId]]");
        $this->subQuery->innerJoin($productDataJoinTable, "[[$productDataTable.id]] = [[shopify_products.shopifyId]]");

        $this->query->select([
            'shopify_products.id',
            'shopify_products.shopifyId',
            'shopify_productdata.status',
            'shopify_productdata.handle',
            'shopify_productdata.productType',
            'shopify_productdata.bodyHtml',
            'shopify_productdata.createdAt',
            'shopify_productdata.publishedAt',
            'shopify_productdata.publishedScope',
            'shopify_productdata.tags',
            'shopify_productdata.templateSuffix',
            'shopify_productdata.updatedAt',
            'shopify_productdata.vendor',
            'shopify_productdata.images',
            'shopify_productdata.options',
            'shopify_productdata.variants',
        ]);

        if (isset($this->shopifyId)) {
            $this->subQuery->andWhere(['shopify_products.shopifyId' => $this->shopifyId]);
        }

        return parent::beforePrepare();
    }
}