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
use craft\shopify\db\Table;
use craft\shopify\elements\Product;
use craft\shopify\Plugin;
use yii\db\Expression;

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

    /**
     * @var mixed|null
     */
    public mixed $shopifyStatus = null;

    /**
     * @var mixed|null
     */
    public mixed $handle = null;


    /**
     * @var mixed|null
     */
    public mixed $productType = null;

    /**
     * @var mixed|null
     */
    public mixed $publishedScope = null;

    /**
     * @var mixed|null
     */
    public mixed $tags = null;

    /**
     * @var mixed|null
     */
    public mixed $vendor = null;

    /**
     * @var mixed|null
     */
    public mixed $images = null;

    /**
     * @var mixed|null
     */
    public mixed $options = null;

    /**
     * @var bool Eager loads all relational data for the resulting products.
     */
    public bool $withAll = false;

    /**
     * Eager loads all relational data for the resulting products.
     *
     * Possible values include:
     *
     * | Value | Fetches all relational data
     * | - | -
     * | bool | `true` to eager-load, `false` to not eager load.
     *
     * @param bool $value The property value
     * @return static self reference
     *
     * @used-by withAll()
     */
    public function withAll(bool $value = true): static
    {
        $this->withAll = $value;

        return $this;
    }

    /**
     * @var bool Eager loads the metafields on the resulting products.
     */
    public bool $withMetafields = false;

    /**
     * Eager loads the metafields on the resulting products.
     *
     * Possible values include:
     *
     * | Value | Fetches metafields
     * | - | -
     * | bool | `true` to eager-load, `false` to not eager load.
     *
     * @param bool $value The property value
     * @return static self reference
     *
     * @used-by withMetafields()
     */
    public function withMetafields(bool $value = true): static
    {
        $this->withMetafields = $value;

        return $this;
    }

    /**
     * @var bool Eager loads the images on the resulting products.
     */
    public bool $withImages = false;

    /**
     * Eager loads the images on the resulting products.
     *
     * Possible values include:
     *
     * | Value | Fetches images
     * | - | -
     * | bool | `true` to eager-load, `false` to not eager load.
     *
     * @param bool $value The property value
     * @return static self reference
     *
     * @used-by withImages()
     */
    public function withImages(bool $value = true): static
    {
        $this->withImages = $value;

        return $this;
    }

    /**
     * @var bool Eager loads the variants on the resulting products.
     */
    public bool $withVariants = false;

    /**
     * Eager loads the variants on the resulting products.
     *
     * Possible values include:
     *
     * | Value | Fetches variants
     * | - | -
     * | bool | `true` to eager-load, `false` to not eager load.
     *
     * @param bool $value The property value
     * @return static self reference
     *
     * @used-by withVariants()
     */
    public function withVariants(bool $value = true): static
    {
        $this->withVariants = $value;

        return $this;
    }

    /**
     * @inheritdoc
     */
    protected array $defaultOrderBy = ['shopify_products.shopifyId' => SORT_ASC];

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
     */
    public function populate($rows): array
    {
        /** @var Product[] $products */
        $products = parent::populate($rows);

        // Eager-load anything?
        if (!empty($products) && !$this->asArray) {

            // Eager-load metafields?
            if ($this->withMetafields === true || $this->withAll) {
                $products = Plugin::getInstance()->getApi()->eagerLoadMetafieldsForProducts($products);
            }

            // Eager-load images?
            if ($this->withImages === true || $this->withAll) {
                $products = Plugin::getInstance()->getApi()->eagerLoadImagesForProducts($products);
            }

            // Eager-load variants?
            if ($this->withVariants === true || $this->withAll) {
                $products = Plugin::getInstance()->getApi()->eagerLoadVariantsForProducts($products);
            }
        }

        return $products;
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

        // join standard product element table that only contains the shopifyId
        $this->joinElementTable('shopify_products');

        $this->query->innerJoin(Table::PRODUCTDATA . ' shopify_productdata', "[[shopify_productdata.shopifyId]] = [[shopify_products.shopifyId]]");
        $this->query->innerJoin(Table::DATA . ' data', new Expression('[[data.shopifyId]] = [[shopify_products.shopifyGid]]'));
        $this->subQuery->innerJoin(Table::PRODUCTDATA . ' shopify_productdata', "[[shopify_productdata.shopifyId]] = [[shopify_products.shopifyId]]");
        $this->subQuery->innerJoin(Table::DATA . ' data', new Expression('[[data.shopifyId]] = [[shopify_products.shopifyGid]]'));

        $this->query->select([
            'shopify_products.shopifyId',
            'shopify_products.shopifyGid',
            'data.shopifyStatus',
            'data.handle',
            'data.productType',
            'data.createdAt',
            'data.publishedAt',
            'shopify_productdata.publishedScope',
            'data.tags',
            'data.templateSuffix',
            'data.updatedAt',
            'data.vendor',
            'data.options',
            'data.data',
        ]);

        if (isset($this->shopifyId)) {
            $this->subQuery->andWhere(Db::parseParam('shopify_products.shopifyId', $this->shopifyId));
        }

        if (isset($this->productType)) {
            $this->subQuery->andWhere(Db::parseParam('data.productType', $this->productType));
        }

        if (isset($this->publishedScope)) {
            $this->subQuery->andWhere(Db::parseParam('data.publishedScope', $this->publishedScope));
        }

        if (isset($this->shopifyStatus)) {
            $this->subQuery->andWhere(Db::parseParam('data.shopifyStatus', $this->shopifyStatus));
        }

        if (isset($this->handle)) {
            $this->subQuery->andWhere(Db::parseParam('data.handle', $this->handle));
        }

        if (isset($this->vendor)) {
            $this->subQuery->andWhere(Db::parseParam('data.vendor', $this->vendor));
        }

        if (isset($this->tags)) {
            $this->subQuery->andWhere(Db::parseParam('data.tags', $this->tags));
        }

        return parent::beforePrepare();
    }
}
