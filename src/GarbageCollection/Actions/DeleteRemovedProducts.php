<?php

declare(strict_types=1);

namespace CraftCms\Shopify\GarbageCollection\Actions;

use craft\shopify\elements\Product;
use CraftCms\Cms\Config\GeneralConfig;
use CraftCms\Cms\Element\Elements;
use CraftCms\Cms\GarbageCollection\Actions\GarbageCollectionAction;
use CraftCms\Cms\GarbageCollection\GarbageCollection;
use CraftCms\Shopify\Api\Api;
use CraftCms\Shopify\Database\Table;
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
        /** @var array<int, string> $shopifyIds Shopify IDs, indexed by element ID */
        $shopifyIds = DB::table(Table::PRODUCTS, 'products')
            ->leftJoin(Table::DATA . ' as data', 'data.shopifyGid', '=', 'products.shopifyGid')
            ->whereNull('data.shopifyGid')
            ->pluck('products.shopifyId', 'products.id')
            ->all();

        if (empty($shopifyIds)) {
            return;
        }

        $this->components->task('deleting Shopify products that no longer exist in Shopify', function() use ($shopifyIds): void {
            $startCursor = null;
            $savedShopifyIds = [];

            foreach (array_chunk($shopifyIds, 100) as $ids) {
                $args = ['first' => 100, 'query' => 'id:' . implode(' OR id:', $ids)];
                if ($startCursor) {
                    $args['after'] = $startCursor;
                }

                $query = (new GqlQuery('products'))
                    ->setArguments($args)
                    ->setSelectionSet([
                        (new GqlQuery('edges'))
                            ->setSelectionSet([
                                (new GqlQuery('node'))
                                    ->setSelectionSet([
                                        'id',
                                    ]),
                            ]),
                        (new GqlQuery('pageInfo'))
                            ->setSelectionSet([
                                'hasNextPage',
                                'endCursor',
                            ]),
                    ]);
                $response = $this->api->query($query);

                if (!$response) {
                    continue;
                }

                $startCursor = $response['pageInfo']['endCursor'];

                $savedShopifyIds = array_merge($savedShopifyIds, array_map(
                    static fn(array $edge): string => str_replace('gid://shopify/Product/', '', $edge['node']['id']),
                    $response['edges'],
                ));
            }

            foreach (array_keys(array_diff($shopifyIds, $savedShopifyIds)) as $elementId) {
                $this->elements->deleteElementById($elementId, Product::class, null, true);
            }
        });
    }
}
