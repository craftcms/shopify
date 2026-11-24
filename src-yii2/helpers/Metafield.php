<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\helpers;

use craft\helpers\Json;
use craft\shopify\records\ShopifyData;

/**
 * Shopify Metafield Helper.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 8.0.0
 */
class Metafield
{
    /**
     * Normalizes an iterable of metafield data into a flat key => value map.
     *
     * Accepts either:
     * - [[ShopifyData]] ActiveRecord objects (from [[Api::getShopifyDataByType()]] with `$returnRecords = true`),
     *   where each record's `data` column holds a JSON-encoded `{key, value}` object.
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
