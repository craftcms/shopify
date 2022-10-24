<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\elements\db;

use craft\db\QueryAbortedException;
use craft\elements\db\ElementQuery;
use craft\shopify\elements\Product;

class ProductQuery extends ElementQuery
{
    /**
     * @var mixed The Shopify product ID(s) that the resulting products must have.
     */
    public mixed $shopifyId = null;

    public ?string $shopifyStatus = null;
    public ?string $handle = null;
    public mixed $productType = null;
    public ?string $bodyHtml = null;
    public ?string $createdAt = null;
    public ?string $publishedAt = null;
    public ?string $publishedScope = null;
    public ?string $tags = null;
    public ?string $templateSuffix = null;
    public ?string $updatedAt = null;
    public ?string $vendor = null;
    public ?string $images = null;
    public ?string $options = null;

    /**
     * @inheritdoc
     */
    protected array $defaultOrderBy = ['shopify_productdata.shopifyId' => SORT_ASC];

    /**
     * @inheritdoc
     */
    public function __construct($elementType, array $config = [])
    {
        // Default status
        if (!isset($config['status'])) {
            $config['status'] = 'live';
        }

        parent::__construct($elementType, $config);
    }

    /**
     * Narrows the query results based on the Shopify product type
     */
    public function productType(mixed $value): self
    {
        $this->productType = $value;
        return $this;
    }

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

    /**
     * Narrows the query results based on the {elements}’ statuses.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `'live'` _(default)_ | that are live (enabled with an Active shopify Status).
     * | `'pending'` | that are pending (enabled with non Active shopify Status).
     * | `'disabled'` | that are disabled in Craft (Regardless of Shopify Status).
     * | `['live', 'pending']` | that are live or pending.
     *
     * ---
     *
     * ```twig
     * {# Fetch disabled {elements} #}
     * {% set {elements-var} = {twig-method}
     *   .status('disabled')
     *   .all() %}
     * ```
     *
     * ```php
     * // Fetch disabled {elements}
     * ${elements-var} = {element-class}::find()
     *     ->status('disabled')
     *     ->all();
     * ```
     */
    public function status(array|string|null $value): ProductQuery
    {
        parent::status($value);
        return $this;
    }

    /**
     * @inheritdoc
     */
    protected function statusCondition(string $status): mixed
    {
        return match ($status) {
            Product::STATUS_LIVE => [
                'elements.enabled' => true,
                'elements_sites.enabled' => true,
                'shopify_productdata.shopifyStatus' => 'active',
            ],
            Product::STATUS_PENDING => [
                'elements.enabled' => true,
                'elements_sites.enabled' => true,
                'shopify_productdata.shopifyStatus' => ['draft', 'archived'],
            ],
            default => parent::statusCondition($status),
        };
    }

    /**
     * @inheritdoc
     * @throws QueryAbortedException
     */
    protected function beforePrepare(): bool
    {
        if ($this->shopifyId === []) {
            return false;
        }

        $productTable = 'shopify_products';
        $productDataTable = 'shopify_productdata';

        // join standard product element table that only contains the shopifyId
        $this->joinElementTable($productTable);

        $productDataJoinTable = [$productDataTable => "{{%$productDataTable}}"];
        $this->query->innerJoin($productDataJoinTable, "[[$productDataTable.shopifyId]] = [[$productTable.shopifyId]]");
        $this->subQuery->innerJoin($productDataJoinTable, "[[$productDataTable.shopifyId]] = [[$productTable.shopifyId]]");

        $this->query->select([
            'shopify_products.shopifyId',
            'shopify_productdata.shopifyStatus',
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
            $this->subQuery->andWhere(['shopify_productdata.shopifyId' => $this->shopifyId]);
        }

        if (isset($this->productType)) {
            $this->subQuery->andWhere(['shopify_productdata.productType' => $this->productType]);
        }

        return parent::beforePrepare();
    }
}
