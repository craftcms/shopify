<?php

declare(strict_types=1);

namespace CraftCms\Shopify\BulkOperation\Models;

use Carbon\CarbonInterface;
use CraftCms\Cms\Shared\BaseModel;
use CraftCms\Cms\Shared\Concerns\HasUid;
use CraftCms\Shopify\Database\Table;

/**
 * A Shopify bulk operation, from being queued locally until its data has been processed.
 *
 * @property int $id
 * @property string|null $shopifyGid
 * @property string|null $url
 * @property string $status
 * @property string|null $shopifyStatus
 * @property string|null $query
 * @property int|null $objectCount
 * @property string $clearData `none`, `all`, or the Shopify GID of the object whose data is cleared first
 * @property CarbonInterface $dateCreated
 * @property CarbonInterface $dateUpdated
 * @property string $uid
 */
class BulkOperation extends BaseModel
{
    use HasUid;

    public const string CLEAR_DATA_NONE = 'none';

    public const string CLEAR_DATA_ALL = 'all';

    #[\Override]
    protected $table = Table::BULK_OPERATIONS;

    #[\Override]
    protected $casts = [
        'objectCount' => 'integer',
    ];
}
