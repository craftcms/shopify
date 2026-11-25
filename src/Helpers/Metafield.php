<?php

declare(strict_types=1);

namespace CraftCms\Shopify\Helpers;

use CraftCms\Cms\Support\Json;
use CraftCms\Shopify\Api\Models\ShopifyData;

/**
 * Shopify metafield helper.
 */
class Metafield
{
    /**
     * Normalizes an iterable of metafield data into a flat key => value map.
     *
     * Accepts either:
     * - [[ShopifyData]] models (from [[Api::getShopifyDataByType()]] with `$returnRecords = true`),
     *   where each model's `data` holds a `{key, value}` object.
     * - Pre-decoded associative arrays (from [[Api::getShopifyDataByType()]] without `$returnRecords`),
     *   where each item already has `key` and `value` keys.
     *
     * Rows that do not carry both `key` and `value` are silently skipped.
     *
     * @param iterable $rows
     * @return array<string, mixed>
     */
    public static function normalizeToMap(iterable $rows): array
    {
        return collect($rows)
            ->mapWithKeys(function($d) {
                $data = match (true) {
                    $d instanceof ShopifyData => Json::decodeIfJson($d->data),
                    is_string($d) => Json::decodeIfJson($d),
                    default => $d,
                };

                if (!is_array($data) || !isset($data['key']) || !isset($data['value'])) {
                    return [];
                }

                return [$data['key'] => Json::decodeIfJson($data['value'])];
            })
            ->all();
    }
}
