<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\elements;

use Craft;
use craft\helpers\App;
use craft\helpers\Cp;
use craft\helpers\DateTimeHelper;
use craft\helpers\Html;
use craft\helpers\StringHelper;
use craft\i18n\Formatter;
use craft\shopify\helpers\Product as ProductHelper;
use craft\shopify\Plugin;
use craft\shopify\records\ProductData;
use craft\shopify\web\assets\shopifycp\ShopifyCpAsset;
use DateTime;
use craft\base\Element;
use craft\elements\conditions\categories\CategoryCondition;
use craft\elements\conditions\ElementConditionInterface;
use craft\elements\User;
use craft\helpers\Json;
use craft\helpers\UrlHelper;
use craft\models\FieldLayout;
use craft\shopify\elements\conditions\products\ProductCondition;
use craft\shopify\elements\db\ProductQuery;
use craft\shopify\records\Product as ProductRecord;

/**
 * Product element.
 *
 */
class Product extends Element
{
    // Shopify Product Statuses
    // -------------------------------------------------------------------------

    /**
     * @since 1.0.0
     */
    public const STATUS_LIVE = 'live';

    /**
     * @var string
     */
    public string $bodyHtml;

    /**
     * @var ?DateTime
     */
    public ?DateTime $createdAt;

    /**
     * @var string
     */
    public string $handle;

    /**
     * @var array
     */
    private array $_images;

    /**
     * @var array
     */
    private array $_options;

    /**
     * @var string
     */
    public string $productType;

    /**
     * @var ?DateTime
     */
    public ?DateTime $publishedAt;

    /**
     * @var string
     */
    public string $publishedScope;

    /**
     * @var string
     */
    public string $status = 'live';

    /**
     * @var string
     */
    public string $tags;

    /**
     * @var string
     */
    public string $templateSuffix;

    /**
     * @var ?DateTime
     */
    public ?DateTime $updatedAt;

    /**
     * @var array
     */
    private array $_variants;

    /**
     * @var string
     */
    public string $vendor;

    /**
     * The product ID in the Shopify store
     *
     * @var int|null
     */
    public ?int $shopifyId = null;

    /**
     * @param string|array $value
     * @return void
     */
    public function setImages(string|array $value): void
    {
        if (is_string($value)) {
            $value = Json::decodeIfJson($value);
        }

        $this->_images = $value;
    }

    /**
     * @return array
     */
    public function getImages(): array
    {
        return $this->_images ?? [];
    }

    /**
     * @param string|array $value
     * @return void
     */
    public function setOptions(string|array $value): void
    {
        if (is_string($value)) {
            $value = Json::decodeIfJson($value);
        }

        $this->_options = $value;
    }

    /**
     * @return array
     */
    public function getOptions(): array
    {
        return $this->_options ?? [];
    }

    /**
     * @param string|array $value
     * @return void
     */
    public function setVariants(string|array $value): void
    {
        if (is_string($value)) {
            $value = Json::decodeIfJson($value);
        }

        $this->_variants = $value;
    }

    /**
     * @return array
     */
    public function getVariants(): array
    {
        return $this->_variants ?? [];
    }

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('app', 'Shopify Product');
    }

    /**
     * @inheritdoc
     */
    public static function lowerDisplayName(): string
    {
        return Craft::t('app', 'shopify category');
    }

    /**
     * @inheritdoc
     */
    public static function pluralDisplayName(): string
    {
        return Craft::t('app', 'Shopify Products');
    }

    /**
     * @inheritdoc
     */
    public static function pluralLowerDisplayName(): string
    {
        return Craft::t('app', 'shopify products');
    }

    /**
     * @inheritdoc
     */
    public static function refHandle(): ?string
    {
        return 'shopify-products';
    }

    /**
     * @inheritdoc
     */
    public static function hasContent(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public static function hasTitles(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public static function hasUris(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public static function isLocalized(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public static function hasStatuses(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function getPostEditUrl(): ?string
    {
        return UrlHelper::cpUrl('shopify/products');
    }

    /**
     *
     * @return string|null
     */
    public function getShopifyEditUrl(): ?string
    {
        $baseUrl = Plugin::getInstance()->getSettings()->hostName;
        return UrlHelper::url('https://' . App::parseEnv($baseUrl) . '/admin/products/' . $this->shopifyId);
    }

    /**
     * @inheritdoc
     */
    public static function trackChanges(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function getUriFormat(): ?string
    {
        return (string)Plugin::getInstance()->getSettings()->uriFormat;
    }

    /**
     * @inheritdoc
     */
    protected function route(): array|string|null
    {
        if (!$this->previewing && $this->getStatus() != self::STATUS_LIVE) {
            return null;
        }

        $settings = Plugin::getInstance()->getSettings();

        if ($url = $settings->uriFormat) {
            return [
                'templates/render', [
                    'template' => (string)$settings->template,
                    'variables' => [
                        'product' => $this,
                    ],
                ],
            ];
        }

        return null;
    }

    /**
     * @inheritdoc
     */
    protected function uiLabel(): ?string
    {
        if (!isset($this->title) || trim($this->title) === '') {
            return Craft::t('shopify', 'Untitled product');
        }

        return null;
    }

    /**
     * @inheritdoc
     * @return ProductQuery The newly created [[ProductQuery]] instance.
     */
    public static function find(): ProductQuery
    {
        return new ProductQuery(static::class);
    }

    /**
     * @inheritdoc
     * @return CategoryCondition
     */
    public static function createCondition(): ElementConditionInterface
    {
        return Craft::createObject(ProductCondition::class, [static::class]);
    }

    /**
     * @return string
     */
    protected function metaFieldsHtml(bool $static): string
    {
        $fields[] = parent::metaFieldsHtml($static);
        return implode("\n", $fields);
    }

    /**
     * @inheritdoc
     */
    public function getFieldLayout(): ?FieldLayout
    {
        return \Craft::$app->fields->getLayoutByType(Product::class);
    }

    /**
     * @return array
     */
    protected function metadata(): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getSidebarHtml(bool $static): string
    {
        Craft::$app->getView()->registerAssetBundle(ShopifyCpAsset::class);
        $productCard = ProductHelper::renderCardHtml($this);
        return $productCard . parent::getSidebarHtml($static);
    }

    /**
     * @inerhitdoc
     */
    public static function defineSources(string $context): array
    {
        return [
            [
                'key' => '*',
                'label' => Craft::t('commerce', 'All products'),
                'criteria' => [
                ],
                'defaultSort' => ['id', 'desc'],
            ]
        ];
    }

    /**
     * @param bool $isNew
     * @return void
     */
    public function afterSave(bool $isNew): void
    {
        if (!$isNew) {
            $record = ProductRecord::findOne($this->id);

            if (!$record) {
                throw new \Exception('Invalid product ID: ' . $this->id);
            }
        } else {
            $record = new ProductRecord();
            $record->id = $this->id;
        }

        $record->shopifyId = $this->shopifyId;

        // We want to always have the same date as the element table, based on the logic for updating these in the element service i.e resaving
        $record->dateUpdated = $this->dateUpdated;
        $record->dateCreated = $this->dateCreated;

        $record->save(false);

        parent::afterSave($isNew);
    }

    /**
     * @return array
     */
    protected static function defineTableAttributes(): array
    {
        return [
            'shopifyId' => Craft::t('shopify', 'Shopify ID'),
            'handle' => Craft::t('shopify', 'Handle'),
            'productType' => Craft::t('shopify', 'Product Type'),
            'tags' => Craft::t('shopify', 'Tags'),
        ];
    }

    /**
     * @inheritdoc
     */
    protected static function defineDefaultTableAttributes(string $source): array
    {
        return [
            'shopifyId',
            'handle',
            'productType',
        ];
    }


    /**
     * @param string $attribute
     * @return string
     * @throws \yii\base\InvalidConfigException
     */
    protected function tableAttributeHtml(string $attribute): string
    {
        switch ($attribute) {
            case 'xxxx':
            {
                return 'xxx';
            }
            default:
            {
                return parent::tableAttributeHtml($attribute);
            }
        }
    }

    /**
     * @inheritdoc
     */
    protected function cpEditUrl(): ?string
    {
        $path = sprintf('shopify/products/%s', $this->getCanonicalId());
        return UrlHelper::cpUrl($path);
    }

    /**
     * @inheritdoc
     */
    public function canCreateDrafts(User $user): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function canSave(User $user): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function canView(User $user): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function canDelete(User $user): bool
    {
        if ($this->getIsDraft()) {
            return true;
        }

        return false;
    }

    /**
     * @inheritdoc
     */
    public function hasRevisions(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        $labels = parent::attributeLabels();

        $labels['bodyHtml'] = Craft::t('shopify', 'Body HTML');
        $labels['createdAt'] = Craft::t('shopify', 'Created At');
        $labels['handle'] = Craft::t('shopify', 'Handle');
        $labels['images'] = Craft::t('shopify', 'Images');
        $labels['options'] = Craft::t('shopify', 'Options');
        $labels['productType'] = Craft::t('shopify', 'Product Type');
        $labels['publishedAt'] = Craft::t('shopify', 'Published At');
        $labels['publishedScope'] = Craft::t('shopify', 'Published Scope');
        $labels['tags'] = Craft::t('shopify', 'Tags');
        $labels['templateSuffix'] = Craft::t('shopify', 'Template Suffix');
        $labels['updatedAt'] = Craft::t('shopify', 'Updated At');
        $labels['variants'] = Craft::t('shopify', 'Variants');
        $labels['vendor'] = Craft::t('shopify', 'Vendor');

        return $labels;
    }

    public function populateFromShopifyData(ProductData $record): void{

//            'bodyHtml' => $record->bodyHtml,
        $this->setAttributes([
            'createdAt' => $record->createdAt,
            'handle' => $record->handle,
            'images' => $record->images,
            'options' => $record->options,
            'productType' => $record->productType,
            'publishedAt' => $record->publishedAt,
            'publishedScope' => $record->publishedScope,
            'shopifyId' => $record->id,
            'status' => $record->status,
            'tags' => $record->tags,
            'templateSuffix' => (string)$record->templateSuffix,
            'title' => $record->title,
            'updatedAt' => $record->updatedAt,
            'variants' => $record->variants,
            'vendor' => $record->vendor,
        ]);
    }
}