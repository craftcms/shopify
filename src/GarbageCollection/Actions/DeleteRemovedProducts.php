<?php

declare(strict_types=1);

namespace CraftCms\Shopify\GarbageCollection\Actions;

use CraftCms\Cms\Config\GeneralConfig;
use CraftCms\Cms\Database\Table as CraftTable;
use CraftCms\Cms\Element\Elements;
use CraftCms\Cms\Element\Queries\ElementQuery;
use CraftCms\Cms\GarbageCollection\Actions\GarbageCollectionAction;
use CraftCms\Cms\GarbageCollection\GarbageCollection;
use CraftCms\Shopify\Api\Api;
use CraftCms\Shopify\Database\Table;
use CraftCms\Shopify\Product\Elements\Product;
use GraphQL\Query as GqlQuery;
use Illuminate\Support\Facades\DB;

/**
 * Hard-deletes product elements that have no Shopify data and no longer exist in Shopify.
 */
class DeleteRemovedProducts extends GarbageCollectionAction
{
    public function __construct(
        GarbageCollection $garbageCollection,
        GeneralConfig $generalConfig,
        private readonly Api $api,
        private readonly Elements $elements,
    ) {
        parent::__construct($garbageCollection, $generalConfig);
    }

    public function __invoke(): void
    {
        $rows = DB::table(Table::PRODUCTS, 'products')
            ->join(CraftTable::ELEMENTS . ' as elements', 'elements.id', '=', 'products.id')
            ->leftJoin(Table::DATA . ' as data', 'data.shopifyGid', '=', 'products.shopifyGid')
            ->whereNull('data.shopifyGid')
            ->whereNull('elements.draftId')
            ->whereNull('elements.revisionId')
            ->get(['products.id', 'products.shopifyId', 'products.shopifyGid'])
            ->keyBy('id');

        /** @var array<int, string> $shopifyIds Shopify IDs, indexed by element ID */
        $shopifyIds = $rows->pluck('shopifyId', 'id')->all();

        if (empty($shopifyIds)) {
            return;
        }

        $this->components->task('deleting Shopify products that no longer exist in Shopify', function() use ($rows, $shopifyIds): void {
            $savedShopifyIds = [];

            foreach (array_chunk($shopifyIds, 100) as $ids) {
                $query = (new GqlQuery('products'))
                    ->setArguments(['first' => 100, 'query' => 'id:' . implode(' OR id:', $ids)])
                    ->setSelectionSet([
                        (new GqlQuery('edges'))
                            ->setSelectionSet([
                                (new GqlQuery('node'))
                                    ->setSelectionSet([
                                        'id',
                                    ]),
                            ]),
                    ]);
                $response = $this->api->query($query);

                // Without an answer from Shopify, these products can't be told apart from removed ones
                if (!$response) {
                    return;
                }

                $savedShopifyIds = array_merge($savedShopifyIds, array_map(
                    static fn(array $edge): string => str_replace('gid://shopify/Product/', '', $edge['node']['id']),
                    $response['edges'],
                ));
            }

            $removedIds = array_keys(array_diff($shopifyIds, $savedShopifyIds));

            if (empty($removedIds)) {
                return;
            }

            // ProductQuery only finds products that have Shopify data, so query the elements table directly
            $products = (new ElementQuery(Product::class))
                ->id($removedIds)
                ->status(null)
                ->site('*')
                ->unique()
                ->all();

            foreach ($products as $product) {
                // afterDelete() needs the GID to delete any variants, media or metafields left behind
                $product->shopifyGid = $rows[$product->id]->shopifyGid;
                $this->elements->deleteElement($product, true);
            }
        });
    }
}
