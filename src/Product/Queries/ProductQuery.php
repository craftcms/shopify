<?php

declare(strict_types=1);

namespace CraftCms\Shopify\Product\Queries;

use Closure;
use CraftCms\Cms\Element\Queries\ElementQuery;
use CraftCms\Cms\Element\Queries\Exceptions\QueryAbortedException;
use CraftCms\Shopify\Database\Table;
use CraftCms\Shopify\Product\Elements\Product;
use CraftCms\Shopify\Product\Products;
use Illuminate\Contracts\Database\Query\Builder as BuilderContract;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Override;

/**
 * @extends ElementQuery<Product>
 */
class ProductQuery extends ElementQuery
{
    /**
     * @var mixed The Shopify product ID(s) that the resulting products must have.
     */
    public mixed $shopifyId = null;

    /**
     * @var mixed The Shopify product GID(s) that the resulting products must have.
     */
    public mixed $shopifyGid = null;

    public mixed $shopifyStatus = null;

    public mixed $handle = null;

    public mixed $productType = null;

    public mixed $tags = null;

    public mixed $templateSuffix = null;

    public mixed $totalInventory = null;

    public mixed $vendor = null;

    public mixed $images = null;

    /**
     * @var bool Eager loads all relational data for the resulting products.
     */
    public bool $withAll = false;

    /**
     * @var bool Eager loads the metafields on the resulting products.
     */
    public bool $withMetafields = false;

    /**
     * @var bool Eager loads the images on the resulting products.
     */
    public bool $withImages = false;

    /**
     * @var bool Eager loads the variants on the resulting products.
     */
    public bool $withVariants = false;

    #[Override]
    protected string $table = Table::PRODUCTS;

    /** @var array<string, int> */
    #[Override]
    protected array $defaultOrderBy = ['shopify_products.shopifyId' => SORT_ASC];

    /** @param array<string, mixed> $config */
    public function __construct(array $config = [])
    {
        if (!isset($config['status'])) {
            $config['status'] = Product::STATUS_LIVE;
        }

        parent::__construct(Product::class, $config);

        $this->query
            ->join(Table::DATA . ' as data', 'data.shopifyGid', '=', 'shopify_products.shopifyGid')
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

        $this->beforeQuery(static function(self $query): void {
            if ($query->shopifyId === []) {
                throw new QueryAbortedException();
            }

            $query->whereParam('shopify_products.shopifyGid', $query->shopifyGid);
            $query->whereParam('shopify_products.shopifyId', $query->shopifyId);
            $query->whereParam('data.totalInventory', $query->totalInventory);
            static::applyProductType($query, $query->productType);
            static::applyShopifyStatus($query, $query->shopifyStatus);
            static::applyHandle($query, $query->handle);
            static::applyVendor($query, $query->vendor);
            static::applyTags($query, $query->tags);
            static::applyTemplateSuffix($query, $query->templateSuffix);
        });
    }

    public static function applyProductType(BuilderContract $query, mixed $value): void
    {
        $query->whereParam('data.productType', $value);
    }

    public static function applyShopifyStatus(BuilderContract $query, mixed $value): void
    {
        $query->whereParam('data.shopifyStatus', $value);
    }

    public static function applyHandle(BuilderContract $query, mixed $value): void
    {
        $query->whereParam('data.handle', $value);
    }

    public static function applyVendor(BuilderContract $query, mixed $value): void
    {
        $query->whereParam('data.vendor', $value);
    }

    public static function applyTags(BuilderContract $query, mixed $value): void
    {
        $query->whereParam('data.tags', $value);
    }

    public static function applyTemplateSuffix(BuilderContract $query, mixed $value): void
    {
        $query->whereParam('data.templateSuffix', $value);
    }

    /**
     * Eager loads all relational data for the resulting products.
     */
    public function withAll(bool $value = true): static
    {
        $this->withAll = $value;

        return $this;
    }

    /**
     * Eager loads the metafields on the resulting products.
     */
    public function withMetafields(bool $value = true): static
    {
        $this->withMetafields = $value;

        return $this;
    }

    /**
     * Eager loads the images on the resulting products.
     */
    public function withImages(bool $value = true): static
    {
        $this->withImages = $value;

        return $this;
    }

    /**
     * Eager loads the variants on the resulting products.
     */
    public function withVariants(bool $value = true): static
    {
        $this->withVariants = $value;

        return $this;
    }

    /**
     * Narrows the query results based on the Shopify product type.
     */
    public function productType(mixed $value): static
    {
        $this->productType = $value;

        return $this;
    }

    /**
     * Narrows the query results based on the Shopify status.
     */
    public function shopifyStatus(mixed $value): static
    {
        $this->shopifyStatus = $value;

        return $this;
    }

    /**
     * Narrows the query results based on the Shopify product handle.
     */
    public function handle(mixed $value): static
    {
        $this->handle = $value;

        return $this;
    }

    /**
     * Narrows the query results based on the Shopify product vendor.
     */
    public function vendor(mixed $value): static
    {
        $this->vendor = $value;

        return $this;
    }

    /**
     * Narrows the query results based on the Shopify product tags.
     */
    public function tags(mixed $value): static
    {
        $this->tags = $value;

        return $this;
    }

    /**
     * Narrows the query results based on the template suffix selected in Shopify.
     */
    public function templateSuffix(mixed $value): static
    {
        $this->templateSuffix = $value;

        return $this;
    }

    public function totalInventory(mixed $value): static
    {
        $this->totalInventory = $value;

        return $this;
    }

    /**
     * Narrows the query results based on the Shopify product ID.
     */
    public function shopifyId(mixed $value): static
    {
        $this->shopifyId = $value;

        return $this;
    }

    /**
     * Narrows the query results based on the Shopify product GID.
     */
    public function shopifyGid(mixed $value): static
    {
        $this->shopifyGid = $value;

        return $this;
    }

    /**
     * Narrows the query results based on the products’ statuses.
     *
     * | Value | Fetches products…
     * | - | -
     * | `'live'` _(default)_ | that are enabled in Craft, with an Active status in Shopify.
     * | `'shopifyDraft'` | that are enabled in Craft, with a Draft status in Shopify.
     * | `'shopifyArchived'` | that are enabled in Craft, with an Archived status in Shopify.
     * | `'disabled'` | that are disabled in Craft, whatever their status in Shopify.
     * | `['live', 'shopifyDraft']` | that are live or drafts in Shopify.
     */
    #[Override]
    public function status(array|string|null $value): static
    {
        return parent::status($value);
    }

    #[Override]
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

    #[Override]
    public function afterHydrate(Collection $elements): Collection
    {
        $elements = parent::afterHydrate($elements);

        if ($elements->isEmpty()) {
            return $elements;
        }

        $products = app(Products::class);
        /** @var Product[] $all */
        $all = $elements->all();

        if ($this->withMetafields || $this->withAll) {
            $all = $products->eagerLoadMetafieldsForProducts($all);
        }

        if ($this->withImages || $this->withAll) {
            $all = $products->eagerLoadImagesForProducts($all);
        }

        if ($this->withVariants || $this->withAll) {
            $all = $products->eagerLoadVariantsForProducts($all);
        }

        return collect($all);
    }
}
