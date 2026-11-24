<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\elements\db;

use Closure;
use craft\elements\db\ElementQuery;
use craft\shopify\elements\Product;
use craft\shopify\Plugin;
use CraftCms\Cms\Element\Queries\Exceptions\QueryAbortedException;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;

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
     * @var mixed The Shopify product GID(s) that the resulting products must have.
     * @since 6.0.0
     */
    public mixed $shopifyGid = null;

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
    public mixed $tags = null;

    /**
     * @var mixed|null
     * @since 7.0.0
     */
    public mixed $templateSuffix = null;

    /**
     * @var mixed|null
     * @since 7.1.0
     */
    public mixed $totalInventory = null;

    /**
     * @var mixed|null
     */
    public mixed $vendor = null;

    /**
     * @var mixed|null
     */
    public mixed $images = null;

    /**
     * @var bool Eager loads all relational data for the resulting products.
     * @since 6.0.0
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
     * @since 6.0.0
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
     * @since 6.0.0
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
     * @since 6.0.0
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
     * @since 6.0.0
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
     * @since 6.0.0
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
     * @since 6.0.0
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
     * @since 6.0.0
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
    protected string $table = 'shopify_products';

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

        $this->query
            ->join('shopify_data as data', 'data.shopifyGid', '=', 'shopify_products.shopifyGid')
            ->addSelect([
                'shopify_products.shopifyId',
                'shopify_products.shopifyGid',
                'data.shopifyStatus',
                'data.handle',
                'data.productType',
                'data.createdAt',
                'data.publishedAt',
                'data.tags',
                'data.templateSuffix',
                'data.updatedAt',
                'data.vendor',
                'data.options',
                'data.totalInventory',
                'data.data',
            ]);

        $this->beforeQuery(static function(ProductQuery $query): void {
            if ($query->shopifyId === []) {
                throw new QueryAbortedException();
            }

            $params = [
                'shopify_products.shopifyGid' => $query->shopifyGid,
                'shopify_products.shopifyId' => $query->shopifyId,
                'data.productType' => $query->productType,
                'data.shopifyStatus' => $query->shopifyStatus,
                'data.handle' => $query->handle,
                'data.vendor' => $query->vendor,
                'data.tags' => $query->tags,
                'data.templateSuffix' => $query->templateSuffix,
                'data.totalInventory' => $query->totalInventory,
            ];

            foreach ($params as $column => $value) {
                if (isset($value)) {
                    $query->query->whereParam($column, $value);
                }
            }
        });
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
     * Narrows the query results based on the “template suffix” selected in Shopify.
     * @since 7.0.0
     */
    public function templateSuffix(mixed $value): ProductQuery
    {
        $this->templateSuffix = $value;
        return $this;
    }

    /**
     * @param mixed $value
     * @return ProductQuery
     * @since 7.1.0
     */
    public function totalInventory(mixed $value): ProductQuery
    {
        $this->totalInventory = $value;
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
     * Narrows the query results based on the Shopify product GID
     * @since 6.0.0
     */
    public function shopifyGid(mixed $value): ProductQuery
    {
        $this->shopifyGid = $value;
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
    protected function statusCondition(string $status): Closure
    {
        $shopifyStatus = match (strtolower($status)) {
            strtolower(Product::STATUS_LIVE) => 'ACTIVE',
            strtolower(Product::STATUS_SHOPIFY_DRAFT) => 'DRAFT',
            strtolower(Product::STATUS_SHOPIFY_ARCHIVED) => 'ARCHIVED',
            default => null,
        };

        if ($shopifyStatus === null) {
            return parent::statusCondition($status);
        }

        return fn(Builder $query) => $query
            ->whereBool('elements.enabled', true)
            ->whereBool('elements_sites.enabled', true)
            ->where('data.shopifyStatus', $shopifyStatus);
    }

    /**
     * @inheritdoc
     */
    public function afterHydrate(Collection $elements): Collection
    {
        $elements = parent::afterHydrate($elements);

        if ($elements->isEmpty()) {
            return $elements;
        }

        /** @var Product[] $products */
        $products = $elements->all();

        if ($this->withMetafields === true || $this->withAll) {
            $products = Plugin::getInstance()->getProducts()->eagerLoadMetafieldsForProducts($products);
        }

        if ($this->withImages === true || $this->withAll) {
            $products = Plugin::getInstance()->getProducts()->eagerLoadImagesForProducts($products);
        }

        if ($this->withVariants === true || $this->withAll) {
            $products = Plugin::getInstance()->getProducts()->eagerLoadVariantsForProducts($products);
        }

        return collect($products);
    }
}
