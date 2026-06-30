<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\collections;

use Craft;
use craft\shopify\models\Variant;
use craft\shopify\records\ShopifyData;
use Illuminate\Support\Collection;

/**
 * VariantCollection represents a collection of Variants.
 *
 * @template TKey of array-key
 * @template TValue of Variant
 * @extends Collection<TKey,TValue>
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 7.0.0
 */
class VariantCollection extends Collection
{
    /**
     * Creates a VariantCollection from an array of Variant attributes.
     *
     * @param array $items
     * @return static
     */
    public static function make($items = [])
    {
        foreach ($items as &$item) {
            if ($item instanceof Variant) {
                continue;
            } elseif (is_array($item)) {
                $item += ['class' => Variant::class];
                $item = \Craft::createObject($item);
            } elseif ($item instanceof ShopifyData) {
                $item = Craft::createObject([
                    'class' => Variant::class,
                    'id' => $item->id,
                    'shopifyGid' => $item->shopifyGid,
                    'shopifyId' => $item->shopifyId,
                    'type' => $item->type,
                    'parentId' => $item->parentId,
                    'dateCreated' => $item->dateCreated,
                    'dateUpdated' => $item->dateUpdated,
                    'uid' => $item->uid,
                    'data' => $item->data,
                ]);
            } else {
                throw new \InvalidArgumentException('Items must be arrays, ShopifyData instances, or Variant instances.');
            }
        }

        /** @var static $collection */
        $collection = parent::make($items);
        return $collection;
    }

    /**
     * Returns the cheapest variant in the collection.
     *
     * @return Variant|null The cheapest variant in the collection, or null if there aren't any
     */
    public function cheapest(): ?Variant
    {
        $variant = $this->sortBy('price')->first();
        return $variant instanceof Variant ? $variant : null;
    }
}
