<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\gql\types;

use Craft;
use craft\gql\base\ObjectType;
use craft\gql\GqlEntityRegistry;
use craft\helpers\Json;
use craft\shopify\models\Variant as VariantElement;
use craft\shopify\Plugin;
use GraphQL\Type\Definition\Type;

/**
 * Class Variant
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 7.1.0
 */
class Variant extends ObjectType
{
    /**
     * @return string
     */
    public static function getName(): string
    {
        return 'ShopifyVariant';
    }

    public static function getType(): Type
    {
        if ($type = GqlEntityRegistry::getEntity(self::getName())) {
            return $type;
        }

        return GqlEntityRegistry::createEntity(self::getName(), new self([
            'name' => static::getName(),
            'fields' => self::class . '::getFieldDefinitions',
            'description' => '',
        ]));
    }

    public static function getFieldDefinitions(): array
    {
        return Craft::$app->getGql()->prepareFieldDefinitions(array_merge(self::_contextualPricingCountriesFields(), [
            'id' => [
                'name' => 'id',
                'type' => Type::string(),
                'description' => 'ID of the variant.',
            ],
            'shopifyId' => [
                'name' => 'shopifyId',
                'type' => Type::string(),
                'description' => 'Shopify ID of the variant.',
            ],
            'shopifyGid' => [
                'name' => 'shopifyGid',
                'type' => Type::string(),
                'description' => 'Shopify GID of the variant.',
            ],
            'title' => [
                'name' => 'title',
                'type' => Type::string(),
                'description' => 'Title of the variant.',
            ],
            'barcode' => [
                'name' => 'barcode',
                'type' => Type::string(),
                'description' => 'Barcode of the variant.',
            ],
            'compareAtPrice' => [
                'name' => 'compareAtPrice',
                'type' => Type::string(),
                'description' => 'Compare at price of the variant.',
            ],
            'createdAt' => [
                'name' => 'createdAt',
                'type' => Type::string(),
                'description' => 'Created at of the variant.',
            ],
            'displayName' => [
                'name' => 'displayName',
                'type' => Type::string(),
                'description' => 'Display name of the variant.',
            ],
            'price' => [
                'name' => 'price',
                'type' => Type::string(),
                'description' => 'Price of the variant.',
            ],
            'sku' => [
                'name' => 'sku',
                'type' => Type::string(),
                'description' => 'Sku of the variant.',
            ],
            'taxable' => [
                'name' => 'taxable',
                'type' => Type::boolean(),
                'description' => 'Taxable price of the variant.',
            ],
            'updatedAt' => [
                'name' => 'updatedAt',
                'type' => Type::string(),
                'description' => 'Updated at of the variant.',
            ],
            'position' => [
                'name' => 'position',
                'type' => Type::int(),
                'description' => 'Position of the variant.',
            ],
            'inventoryPolicy' => [
                'name' => 'inventoryPolicy',
                'type' => Type::string(),
                'description' => 'Inventory policy of the variant.',
            ],
            'inventoryQuantity' => [
                'name' => 'inventoryQuantity',
                'type' => Type::int(),
                'description' => 'Inventory quantity of the variant.',
            ],
            'metafields' => [
                'name' => 'metafields',
                'type' => Type::listOf(Metafield::getType()),
                'description' => 'Metafields of the variant.',
                'resolve' => function(VariantElement $source) {
                    return collect($source->getMetafields())
                        // Ensure value is encoded as we aren't sure of its type
                        ->map(fn($value, $key) => ['key' => $key, 'value' => !is_string($value) ? Json::encode($value) : $value])
                        ->all();
                },
            ],
            'selectedOptions' => [
                'name' => 'selectedOptions',
                'type' => Type::listOf(GqlEntityRegistry::getOrCreate('ShopifyVariantSelectionOption', fn() => new \GraphQL\Type\Definition\ObjectType([
                    'name' => 'ShopifyVariantSelectionOption',
                    'fields' => [
                        'name' => [
                            'name' => 'name',
                            'type' => Type::string(),
                        ],
                        'value' => [
                            'name' => 'value',
                            'type' => Type::string(),
                        ],
                    ],
                ]))),
            ],
            'product' => [
                'name' => 'product',
                'type' => GqlEntityRegistry::getOrCreate('ShopifyVariantProduct', fn() => new \GraphQL\Type\Definition\ObjectType([
                    'name' => 'ShopifyVariantProduct',
                    'fields' => [
                        'id' => [
                            'name' => 'id',
                            'type' => Type::string(),
                        ],
                    ],
                ])),
            ],
            'image' => [
                'name' => 'image',
                'type' => GqlEntityRegistry::getOrCreate('ShopifyVariantImage', fn() => new \GraphQL\Type\Definition\ObjectType([
                    'name' => 'ShopifyVariantImage',
                    'fields' => [
                        'altText' => [
                            'name' => 'altText',
                            'type' => Type::string(),
                        ],
                        'height' => [
                            'name' => 'height',
                            'type' => Type::int(),
                        ],
                        'id' => [
                            'name' => 'id',
                            'type' => Type::string(),
                        ],
                        'url' => [
                            'name' => 'url',
                            'type' => Type::string(),
                        ],
                        'width' => [
                            'name' => 'width',
                            'type' => Type::int(),
                        ],
                        'originalSrc' => [
                            'name' => 'originalSrc',
                            'type' => Type::string(),
                        ],
                        'src' => [
                            'name' => 'src',
                            'type' => Type::string(),
                        ],
                        'transformedSrc' => [
                            'name' => 'transformedSrc',
                            'type' => Type::string(),
                        ],
                    ],
                ])),
            ],
            'inventoryItem' => [
                'name' => 'inventoryItem',
                'type' => GqlEntityRegistry::getOrCreate('ShopifyVariantInventoryItem', fn() => new \GraphQL\Type\Definition\ObjectType([
                    'name' => 'ShopifyVariantInventoryItem',
                    'fields' => [
                        'id' => [
                            'name' => 'id',
                            'type' => Type::string(),
                        ],
                        'countryCodeOfOrigin' => [
                            'name' => 'countryCodeOfOrigin',
                            'type' => Type::string(),
                        ],
                        'createdAt' => [
                            'name' => 'createdAt',
                            'type' => Type::string(),
                        ],
                        'updatedAt' => [
                            'name' => 'updatedAt',
                            'type' => Type::string(),
                        ],
                        'sku' => [
                            'name' => 'sku',
                            'type' => Type::string(),
                        ],
                        'tracked' => [
                            'name' => 'tracked',
                            'type' => Type::boolean(),
                        ],
                        'unitCost' => [
                            'name' => 'unitCost',
                            'type' => GqlEntityRegistry::getOrCreate('ShopifyVariantInventoryItemUnitCost', fn() => new \GraphQL\Type\Definition\ObjectType([
                                'name' => 'ShopifyVariantInventoryItemUnitCost',
                                'fields' => [
                                    'amount' => [
                                        'name' => 'amount',
                                        'type' => Type::string(),
                                    ],
                                    'currencyCode' => [
                                        'name' => 'currencyCode',
                                        'type' => Type::string(),
                                    ],
                                ],
                            ])),
                        ],
                    ],
                ])),
            ],
        ]), self::getName());
    }

    private static function _contextualPricingCountriesFields(): array
    {
        $contextualPricingCountries = Plugin::getInstance()->getSettings()->getContextualPricingCountries();
        if (empty($contextualPricingCountries)) {
            return [];
        }

        $contextualPricing = [];

        $contextualPricingCountries = explode(',', $contextualPricingCountries);
        foreach ($contextualPricingCountries as $country) {
            // Keys cannot contain whitespace:
            $key = trim($country);

            // Empty key, or not the right length?
            if (!$key || strlen($key) !== 2) {
                continue;
            }

            $name = 'ShopifyVariantContextualPrice' . ucfirst(strtolower($key));

            $contextualPricing[strtolower($key) . 'ContextualPricing'] = [
                'name' => strtolower($key) . 'ContextualPricing',
                'type' => GqlEntityRegistry::getOrCreate($name, fn() => new \GraphQL\Type\Definition\ObjectType([
                    'name' => $name,
                    'fields' => [
                        'price' => [
                            'name' => 'price',
                            'type' => GqlEntityRegistry::getOrCreate($name . 'Price', fn() => new \GraphQL\Type\Definition\ObjectType([
                                'name' => $name . 'Price',
                                'fields' => [
                                    'amount' => [
                                        'name' => 'amount',
                                        'type' => Type::string(),
                                        'description' => '',
                                    ],
                                    'currencyCode' => [
                                        'name' => 'currencyCode',
                                        'type' => Type::string(),
                                        'description' => '',
                                    ],
                                ],
                            ])),
                        ],
                        'compareAtPrice' => [
                            'name' => 'compareAtPrice',
                            'type' => GqlEntityRegistry::getOrCreate($name . 'CompareAtPrice', fn() => new \GraphQL\Type\Definition\ObjectType([
                                'name' => $name . 'CompareAtPrice',
                                'fields' => [
                                    'amount' => [
                                        'name' => 'amount',
                                        'type' => Type::string(),
                                        'description' => '',
                                    ],
                                    'currencyCode' => [
                                        'name' => 'currencyCode',
                                        'type' => Type::string(),
                                        'description' => '',
                                    ],
                                ],
                            ])),
                        ],
                    ],
                ])),
            ];
        }

        return $contextualPricing;
    }
}
