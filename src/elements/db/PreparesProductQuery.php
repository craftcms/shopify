<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\elements\db;

use Closure;
use craft\db\QueryAbortedException;
use craft\helpers\Db;
use craft\shopify\db\Table;
use craft\shopify\elements\Product;
use CraftCms\Cms\Element\Queries\ElementQuery as Craft6ElementQuery;
use CraftCms\Cms\Element\Queries\Exceptions\QueryAbortedException as Craft6QueryAbortedException;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use yii\db\Expression;

if (!class_exists(Craft6ElementQuery::class)) {
    /**
     * Prepares product queries on Craft 5.
     *
     * @internal
     * @mixin ProductQuery
     */
    trait PreparesProductQuery
    {
        private function initProductQuery(): void
        {
        }

        /**
         * @inheritdoc
         */
        protected function statusCondition(string $status): mixed
        {
            return match ($status) {
                strtolower(Product::STATUS_LIVE) => [
                    'elements.enabled' => true,
                    'elements_sites.enabled' => true,
                    'data.shopifyStatus' => 'ACTIVE',
                ],
                strtolower(Product::STATUS_SHOPIFY_DRAFT) => [
                    'elements.enabled' => true,
                    'elements_sites.enabled' => true,
                    'data.shopifyStatus' => 'DRAFT',
                ],
                strtolower(Product::STATUS_SHOPIFY_ARCHIVED) => [
                    'elements.enabled' => true,
                    'elements_sites.enabled' => true,
                    'data.shopifyStatus' => 'ARCHIVED',
                ],
                default => parent::statusCondition($status),
            };
        }

        /**
         * @inheritdoc
         */
        public function populate($rows): array
        {
            /** @var Product[] $products */
            $products = parent::populate($rows);

            if (!empty($products) && !$this->asArray) {
                $products = $this->eagerLoadShopifyData($products);
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

            $this->query->innerJoin(Table::DATA . ' data', new Expression('[[data.shopifyGid]] = [[shopify_products.shopifyGid]]'));
            $this->subQuery->innerJoin(Table::DATA . ' data', new Expression('[[data.shopifyGid]] = [[shopify_products.shopifyGid]]'));

            $this->query->select([
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

            if (isset($this->shopifyGid)) {
                $this->subQuery->andWhere(Db::parseParam('shopify_products.shopifyGid', $this->shopifyGid));
            }

            if (isset($this->shopifyId)) {
                $this->subQuery->andWhere(Db::parseParam('shopify_products.shopifyId', $this->shopifyId));
            }

            if (isset($this->productType)) {
                $this->subQuery->andWhere(Db::parseParam('data.productType', $this->productType));
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

            if (isset($this->templateSuffix)) {
                $this->subQuery->andWhere(Db::parseParam('data.templateSuffix', $this->templateSuffix));
            }

            if (isset($this->totalInventory)) {
                $this->subQuery->andWhere(Db::parseParam('data.totalInventory', $this->totalInventory));
            }

            return parent::beforePrepare();
        }
    }
} else {
    /**
     * Prepares product queries on Craft 6, where element queries are built with Laravel’s query builder.
     *
     * Craft 6 no longer calls `beforePrepare()` or `populate()`, so the product table is declared with `$table`,
     * the Shopify data table is joined when the query is created, and params are applied right before it runs.
     *
     * @internal
     * @mixin ProductQuery
     */
    trait PreparesProductQuery
    {
        /**
         * @inheritdoc
         */
        protected string $table = 'shopify_products';

        private function initProductQuery(): void
        {
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
                    throw new Craft6QueryAbortedException();
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

            return collect($this->eagerLoadShopifyData($elements->all()));
        }
    }
}
