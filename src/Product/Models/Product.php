<?php

declare(strict_types=1);

namespace CraftCms\Shopify\Product\Models;

use CraftCms\Cms\Element\Models\Element;
use CraftCms\Cms\Shared\BaseModel;
use CraftCms\Cms\Shared\Concerns\HasUid;
use CraftCms\Shopify\Api\Models\ShopifyData;
use CraftCms\Shopify\Database\Table;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * The `shopify_products` row of a product element.
 *
 * Its dates are copied from the element when the element is saved, so Eloquent doesn’t set them.
 *
 * @property int $id
 * @property int|string|null $shopifyId
 * @property string|null $shopifyGid
 * @property DateTimeInterface|null $dateCreated
 * @property DateTimeInterface|null $dateUpdated
 * @property string $uid
 */
class Product extends BaseModel
{
    use HasUid;

    #[\Override]
    protected $table = Table::PRODUCTS;

    #[\Override]
    public $timestamps = false;

    /**
     * `id` is the element ID, not an auto-increment column.
     */
    #[\Override]
    public $incrementing = false;

    #[\Override]
    protected $casts = [
        'dateCreated' => 'datetime',
        'dateUpdated' => 'datetime',
    ];

    /**
     * @return BelongsTo<Element, $this>
     */
    public function element(): BelongsTo
    {
        return $this->belongsTo(Element::class, 'id');
    }

    /**
     * @return HasOne<ShopifyData, $this>
     */
    public function data(): HasOne
    {
        return $this->hasOne(ShopifyData::class, 'shopifyGid', 'shopifyGid');
    }
}
