<?php

declare(strict_types=1);

namespace CraftCms\Shopify\Api\Models;

use Carbon\CarbonInterface;
use CraftCms\Cms\Shared\BaseModel;
use CraftCms\Cms\Shared\Concerns\HasUid;
use CraftCms\Shopify\Database\Table;

/**
 * An object synced from Shopify, such as a product, variant, metafield, image or the shop.
 *
 * The product columns (`handle`, `productType`, `shopifyStatus`, …) and `shopifyId` are generated from `data`, so they
 * can be read but not written.
 *
 * @property int $id
 * @property string|null $shopifyGid
 * @property-read string|null $shopifyId
 * @property string|null $type
 * @property array|null $data
 * @property string|null $parentId
 * @property int|null $position The line index of the item in its bulk operation file
 * @property CarbonInterface $dateCreated
 * @property CarbonInterface $dateUpdated
 * @property string $uid
 */
class ShopifyData extends BaseModel
{
    use HasUid;

    #[\Override]
    protected $table = Table::DATA;

    #[\Override]
    protected $casts = [
        'data' => 'array',
        'position' => 'integer',
    ];
}
