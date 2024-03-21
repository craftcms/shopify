<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\elements\db;

use craft\db\QueryAbortedException;
use craft\elements\db\ElementQuery;
use craft\helpers\Db;
use craft\shopify\elements\Product;

/**
 * ProductQuery represents a SELECT SQL statement for entries in a way that is independent of DBMS.
 *
 * @method Product[]|array all($db = null)
 * @method Product|array|null one($db = null)
 * @method Product|array|null nth(int $n, ?Connection $db = null)
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 */
class ProductQuery extends ElementQuery
{
    /**
     * @var mixed The Shopify product ID(s) that the resulting products must have.
     */
    public mixed $shopifyId = null;

    public mixed $shopifyStatus = null;
    public mixed $handle = null;
    public mixed $productType = null;
    public mixed $publishedScope = null;
    public mixed $tags = null;
    public mixed $vendor = null;
    public mixed $images = null;
    public mixed $options = null;

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
     * Narrows the query results based on the Shopify product type
     */
    public function publishedScope(mixed $value): self
    {
        $this->publishedScope = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the Shopify status
     */
    public function shopifyStatus(mixed $value): self
    {
        $this->shopifyStatus = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the Shopify product handle
     */
    public function handle(mixed $value): self
    {
        $this->handle = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the Shopify product vendor
     */
    public function vendor(mixed $value): self
    {
        $this->vendor = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the Shopify product tags
     */
    public function tags(mixed $value): self
    {
        $this->tags = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the Shopify product ID
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
     * | `'live'` _(default)_ | that are live (enabled in Craft, with an Active shopify Status).
     * | `'shopifyDraft'` | that are enabled with a Draft shopify Status.
     * | `'shopifyArchived'` | that are enabled, with an Archived shopify Status.
     * | `'disabled'` | that are disabled in Craft (Regardless of Shopify Status).
     * | `['live', 'shopifyDraft']` | that are live or shopify draft.
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
    public function status(array|string|null $value): static
    {
        parent::status($value);
        return $this;
    }

    /**
     * @inheritdoc
     */
    protected function statusCondition(string $status): mixed
    {
        $res = match ($status) {
            strtolower(Product::STATUS_LIVE) => [
                'elements.enabled' => true,
                'elements_sites.enabled' => true,
                'shopify_productdata.shopifyStatus' => 'active',
            ],
            strtolower(Product::STATUS_SHOPIFY_DRAFT) => [
                'elements.enabled' => true,
                'elements_sites.enabled' => true,
                'shopify_productdata.shopifyStatus' => 'draft',
            ],
            strtolower(Product::STATUS_SHOPIFY_ARCHIVED) => [
                'elements.enabled' => true,
                'elements_sites.enabled' => true,
                'shopify_productdata.shopifyStatus' => 'archived',
            ],
            default => parent::statusCondition($status),
        };

        return $res;
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
            'shopify_productdata.metaFields',
            'shopify_productdata.images',
            'shopify_productdata.options',
            'shopify_productdata.variants',
        ]);

        if (isset($this->shopifyId)) {
            $this->subQuery->andWhere(Db::parseParam('shopify_productdata.shopifyId', $this->shopifyId));
        }

        if (isset($this->productType)) {
            $this->subQuery->andWhere(Db::parseParam('shopify_productdata.productType', $this->productType));
        }

        if (isset($this->publishedScope)) {
            $this->subQuery->andWhere(Db::parseParam('shopify_productdata.publishedScope', $this->publishedScope));
        }

        if (isset($this->shopifyStatus)) {
            $this->subQuery->andWhere(Db::parseParam('shopify_productdata.shopifyStatus', $this->shopifyStatus));
        }

        if (isset($this->handle)) {
            $this->subQuery->andWhere(Db::parseParam('shopify_productdata.handle', $this->handle));
        }

        if (isset($this->vendor)) {
            $this->subQuery->andWhere(Db::parseParam('shopify_productdata.vendor', $this->vendor));
        }

        if (isset($this->tags)) {
            $this->subQuery->andWhere(Db::parseParam('shopify_productdata.tags', $this->tags));
        }

        return parent::beforePrepare();
    }
}
