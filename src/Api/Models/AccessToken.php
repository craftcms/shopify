<?php

declare(strict_types=1);

namespace CraftCms\Shopify\Api\Models;

use Carbon\CarbonInterface;
use CraftCms\Cms\Shared\BaseModel;
use CraftCms\Cms\Shared\Concerns\HasUid;
use CraftCms\Shopify\Database\Table;

/**
 * The stored Shopify access token: an env var reference, or the token encrypted with `Str::encenc()`.
 *
 * @property int $id
 * @property string|null $accessToken
 * @property CarbonInterface $dateCreated
 * @property CarbonInterface $dateUpdated
 * @property string $uid
 */
class AccessToken extends BaseModel
{
    use HasUid;

    #[\Override]
    protected $table = Table::ACCESS_TOKENS;
}
