<?php

declare(strict_types=1);

namespace CraftCms\Shopify\Product\Elements;

use craft\shopify\collections\VariantCollection;
use craft\shopify\fieldlayoutelements\MetafieldsField;
use craft\shopify\fieldlayoutelements\OptionsField;
use craft\shopify\fieldlayoutelements\VariantsField;
use craft\shopify\models\Variant;
use CraftCms\Cms\Cp\Data\ActionItem;
use CraftCms\Cms\Element\Conditions\Contracts\ElementConditionInterface;
use CraftCms\Cms\Element\Element;
use CraftCms\Cms\FieldLayout\FieldLayout;
use CraftCms\Cms\Support\Facades\Fields;
use CraftCms\Cms\Support\Html;
use CraftCms\Cms\Support\Json;
use CraftCms\Cms\Support\Str;
use CraftCms\Cms\Support\Url;
use CraftCms\Shopify\Api\Api;
use CraftCms\Shopify\Helpers\Metafield as MetafieldHelper;
use CraftCms\Shopify\Helpers\Product as ProductHelper;
use CraftCms\Shopify\Product\Conditions\ProductCondition;
use CraftCms\Shopify\Product\Models\Product as ProductRecord;
use CraftCms\Shopify\Product\Products;
use CraftCms\Shopify\Product\Queries\ProductQuery;
use CraftCms\Shopify\Settings;
use CraftCms\Shopify\Store\Store;
use DateTime;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use InvalidArgumentException;
use Override;
use RuntimeException;

use function CraftCms\Cms\t;

/**
 * Product element.
 *
 * @property array $tags
 * @property array $options
 */
class Product extends Element
{
    public const string STATUS_LIVE = 'live';

    public const string STATUS_SHOPIFY_DRAFT = 'shopifyDraft';

    public const string STATUS_SHOPIFY_ARCHIVED = 'shopifyArchived';

    public const string SHOPIFY_STATUS_ACTIVE = 'active';

    public const string SHOPIFY_STATUS_DRAFT = 'draft';

    public const string SHOPIFY_STATUS_ARCHIVED = 'archived';

    public const string GQL_TYPE_NAME = 'ShopifyProduct';

    public ?DateTime $createdAt = null;

    public ?string $handle = null;

    public ?string $productType = null;

    public ?DateTime $publishedAt = null;

    /**
     * @var int|null The product ID in the Shopify store
     */
    public ?int $shopifyId = null;

    public ?string $shopifyGid = null;

    public string $shopifyStatus = self::SHOPIFY_STATUS_ACTIVE;

    public ?string $templateSuffix = null;

    public ?int $totalInventory = null;

    public ?DateTime $updatedAt = null;

    public ?string $vendor = null;

    /**
     * @var bool Whether the product is being saved from Shopify data by a sync or webhook. These saves don't create
     * revisions, because a revision only keeps what Craft stores, and the next sync would overwrite it anyway.
     */
    public bool $syncing = false;

    private ?array $_images = null;

    private array $_options = [];

    private ?array $_metaFields = null;

    private array $_tags = [];

    private ?VariantCollection $_variants = null;

    /**
     * @see self::getData()
     * @see self::setData()
     */
    private ?array $_data = null;

    public function __construct($config = [])
    {
        parent::__construct($config);
        $this->title = $this->title ? Str::shortcodesToEmoji($this->title) : null;
    }

    #[Override]
    public static function displayName(): string
    {
        return t('Product', category: 'shopify');
    }

    #[Override]
    public static function lowerDisplayName(): string
    {
        return t('Shopify product', category: 'shopify');
    }

    #[Override]
    public static function pluralDisplayName(): string
    {
        return t('Shopify Products', category: 'shopify');
    }

    #[Override]
    public static function pluralLowerDisplayName(): string
    {
        return t('Shopify products', category: 'shopify');
    }

    #[Override]
    public static function refHandle(): ?string
    {
        return 'shopifyproduct';
    }

    #[Override]
    public static function hasTitles(): bool
    {
        return true;
    }

    #[Override]
    public static function hasUris(): bool
    {
        return true;
    }

    #[Override]
    public static function isLocalized(): bool
    {
        return true;
    }

    #[Override]
    public static function hasStatuses(): bool
    {
        return true;
    }

    #[Override]
    public static function trackChanges(): bool
    {
        return true;
    }

    #[Override]
    public static function supportsRevisions(): bool
    {
        return true;
    }

    #[Override]
    public static function statuses(): array
    {
        return [
            self::STATUS_LIVE => t('Live', category: 'shopify'),
            self::STATUS_SHOPIFY_DRAFT => ['label' => t('Draft in Shopify', category: 'shopify'), 'color' => 'orange'],
            self::STATUS_SHOPIFY_ARCHIVED => ['label' => t('Archived in Shopify', category: 'shopify'), 'color' => 'red'],
            self::STATUS_DISABLED => t('Disabled'),
        ];
    }

    #[Override]
    public static function find(): ProductQuery
    {
        return new ProductQuery();
    }

    /**
     * @return ProductCondition
     */
    #[Override]
    public static function createCondition(): ElementConditionInterface
    {
        return new ProductCondition(static::class);
    }

    #[Override]
    public static function gqlScopesByContext(mixed $context): array
    {
        return [self::GQL_TYPE_NAME];
    }

    #[Override]
    protected static function defineSources(string $context): array
    {
        return [
            [
                'key' => '*',
                'label' => t('All products', category: 'shopify'),
                'criteria' => [],
                'defaultSort' => ['id', 'desc'],
            ],
        ];
    }

    #[Override]
    protected static function defineSearchableAttributes(): array
    {
        return array_merge(parent::defineSearchableAttributes(), [
            'descriptionHtml',
            'handle',
            'vendor',
            'productType',
            'tags',
            'options',
            'metafields',
        ]);
    }

    #[Override]
    protected static function defineTableAttributes(): array
    {
        return [
            'shopifyId' => ['label' => t('Shopify ID', category: 'shopify')],
            'createdAt' => ['label' => t('Created At', category: 'shopify')],
            'handle' => ['label' => t('Handle', category: 'shopify')],
            'options' => ['label' => t('Options', category: 'shopify')],
            'productType' => ['label' => t('Product Type', category: 'shopify')],
            'publishedAt' => ['label' => t('Published At', category: 'shopify')],
            'shopifyStatus' => ['label' => t('Shopify Status', category: 'shopify')],
            'tags' => ['label' => t('Tags', category: 'shopify')],
            'updatedAt' => ['label' => t('Updated At', category: 'shopify')],
            'variants' => ['label' => t('Variants', category: 'shopify')],
            'vendor' => ['label' => t('Vendor', category: 'shopify')],
            'templateSuffix' => ['label' => t('Template suffix', category: 'shopify')],
            'shopifyEdit' => ['label' => t('Shopify Edit', category: 'shopify')],
        ];
    }

    #[Override]
    protected static function defineDefaultTableAttributes(string $source): array
    {
        return [
            'shopifyId',
            'shopifyStatus',
            'handle',
            'productType',
        ];
    }

    #[Override]
    protected static function defineSortOptions(): array
    {
        // Variants and the Shopify edit link aren't columns, so they can't be sorted by
        return [
            ...Arr::except(parent::defineSortOptions(), ['variants', 'shopifyEdit']),
            'title' => [
                'label' => t('Title'),
                'orderBy' => 'data.title',
                'defaultDir' => 'desc',
            ],
            'shopifyId' => [
                'label' => t('Shopify ID', category: 'shopify'),
                'orderBy' => 'data.shopifyId',
                'defaultDir' => 'desc',
            ],
            'shopifyStatus' => [
                'label' => t('Shopify Status', category: 'shopify'),
                'orderBy' => 'data.shopifyStatus',
                'defaultDir' => 'desc',
            ],
        ];
    }

    public function getDescriptionHtml(): ?string
    {
        $descriptionHtml = Arr::get($this->getData(), 'descriptionHtml');
        if ($descriptionHtml === null) {
            return null;
        }

        return Str::shortcodesToEmoji($descriptionHtml);
    }

    public function setData(array|string|null $data): void
    {
        if ($data === null || is_array($data)) {
            $this->_data = $data;

            return;
        }

        $this->_data = Json::decodeIfJson($data);
    }

    public function getData(): array
    {
        return $this->_data ?? [];
    }

    #[Override]
    public function getGqlTypeName(): string
    {
        return self::GQL_TYPE_NAME;
    }

    #[Override]
    public function getStatus(): ?string
    {
        $status = parent::getStatus();

        if ($status === self::STATUS_ENABLED) {
            return match ($this->shopifyStatus) {
                self::SHOPIFY_STATUS_DRAFT => self::STATUS_SHOPIFY_DRAFT,
                self::SHOPIFY_STATUS_ARCHIVED => self::STATUS_SHOPIFY_ARCHIVED,
                default => self::STATUS_LIVE,
            };
        }

        return $status;
    }

    public function setTags(array|string $tags): void
    {
        if (is_string($tags)) {
            $tags = Json::decodeIfJson($tags);
        }

        $this->_tags = $tags;
    }

    public function getTags(): array
    {
        return $this->_tags;
    }

    public function setImages(string|array $value): void
    {
        if (is_string($value)) {
            $value = Json::decodeIfJson($value);
        }

        $this->_images = $value;
    }

    public function getImages(): array
    {
        if (!$this->shopifyGid) {
            return [];
        }

        if ($this->_images !== null) {
            return $this->_images;
        }

        $this->setImages(app(Api::class)->getShopifyDataByType('MediaImage', $this->shopifyGid)->all());

        return $this->_images ?? [];
    }

    public function setOptions(string|array $value): void
    {
        if (is_string($value)) {
            $value = Json::decodeIfJson($value);
        }

        $this->_options = $value;
    }

    public function getOptions(): array
    {
        return $this->_options;
    }

    /**
     * @param string|array $value A list-shaped array of `{key, value}` objects, or a JSON-encoded string of the same.
     * @throws InvalidArgumentException if the value is not a list-shaped array or JSON string of one.
     */
    public function setMetafields(string|array $value): void
    {
        if (is_string($value)) {
            $value = Json::decodeIfJson($value);
        }

        if (!is_array($value) || !array_is_list($value)) {
            throw new InvalidArgumentException('setMetafields() expects a list-shaped array of {key, value} objects or a JSON-encoded string of the same.');
        }

        $this->_metaFields = MetafieldHelper::normalizeToMap($value);
    }

    public function getMetafields(): array
    {
        if (!$this->shopifyGid) {
            return [];
        }

        if ($this->_metaFields !== null) {
            return $this->_metaFields;
        }

        $this->setMetafields(app(Api::class)->getShopifyDataByType('Metafield', $this->shopifyGid)->all());

        return $this->_metaFields ?? [];
    }

    /**
     * @param string|array|Collection<int, Variant> $value
     */
    public function setVariants(string|array|Collection $value): void
    {
        if (is_string($value)) {
            $value = Json::decodeIfJson($value);
        }

        if (is_array($value)) {
            $value = VariantCollection::make($value);
        } elseif (!$value instanceof VariantCollection) {
            $value = VariantCollection::make($value->all());
        }

        $this->_variants = $value;
    }

    public function getVariants(): VariantCollection
    {
        if (!$this->shopifyGid) {
            return VariantCollection::make();
        }

        if ($this->_variants === null) {
            $this->setVariants(app(Api::class)->getShopifyDataByType('ProductVariant', $this->shopifyGid, true));
        }

        return $this->_variants ?? VariantCollection::make();
    }

    #[Override]
    public function attributes(): array
    {
        $attributes = array_values(array_diff(parent::attributes(), ['syncing']));
        $attributes[] = 'images';
        $attributes[] = 'metafields';
        $attributes[] = 'variants';

        return $attributes;
    }

    #[Override]
    public function attributeLabels(): array
    {
        return [
            ...parent::attributeLabels(),
            'shopifyId' => t('Shopify ID', category: 'shopify'),
            'createdAt' => t('Created at', category: 'shopify'),
            'handle' => t('Handle', category: 'shopify'),
            'images' => t('Images', category: 'shopify'),
            'options' => t('Options', category: 'shopify'),
            'productType' => t('Product Type', category: 'shopify'),
            'publishedAt' => t('Published at', category: 'shopify'),
            'tags' => t('Tags', category: 'shopify'),
            'shopifyStatus' => t('Status', category: 'shopify'),
            'templateSuffix' => t('Template suffix', category: 'shopify'),
            'updatedAt' => t('Updated at', category: 'shopify'),
            'variants' => t('Variants', category: 'shopify'),
            'vendor' => t('Vendor', category: 'shopify'),
            'metaFields' => t('Meta Fields', category: 'shopify'),
        ];
    }

    /**
     * Gets the cheapest variant.
     */
    public function getCheapestVariant(): ?Variant
    {
        return $this->getVariants()->cheapest();
    }

    /**
     * Gets the first variant, which is Shopify’s default variant.
     */
    public function getDefaultVariant(): ?Variant
    {
        return $this->getVariants()->first();
    }

    #[Override]
    public function getTitleTranslationKey(): string
    {
        // The title always comes from Shopify, and is the same in every site.
        return '';
    }

    #[Override]
    public function hasRevisions(): bool
    {
        return !$this->syncing;
    }

    public function getShopifyStatusHtml(): string
    {
        return ProductHelper::shopifyStatusHtml($this);
    }

    #[Override]
    public function getPostEditUrl(): ?string
    {
        return Url::cpUrl('shopify/products');
    }

    public function getShopifyEditUrl(): ?string
    {
        return app(Store::class)->getUrl("admin/products/{$this->shopifyId}");
    }

    public function getShopifyUrl(array $params = []): string
    {
        return app(Store::class)->getUrl("products/{$this->handle}", $params);
    }

    /**
     * @see self::getLink()
     * @see self::getShopifyUrl()
     */
    public function getShopifyLink(?string $text = null, array $attributes = []): HtmlString
    {
        return new HtmlString(Html::a($text ?: $this->title, $this->getShopifyUrl(), $attributes));
    }

    #[Override]
    public function getUriFormat(): ?string
    {
        return app(Settings::class)->uriFormat;
    }

    #[Override]
    public function getFieldLayout(): ?FieldLayout
    {
        return Fields::getLayoutByType(self::class);
    }

    #[Override]
    protected function crumbs(): array
    {
        return [
            new ActionItem()->label(t('Shopify', category: 'shopify'))->href(Url::cpUrl('shopify')),
            new ActionItem()->label(t('Products', category: 'shopify'))->href(Url::cpUrl('shopify/products')),
        ];
    }

    /**
     * Shows the Shopify details below the element’s own metadata. Variants, options and metafields are left out when
     * the field layout already shows them.
     */
    #[Override]
    public function getMetadataGroups(): array
    {
        $exclude = [];

        foreach ($this->getFieldLayout()?->getTabs() ?? [] as $tab) {
            foreach ($tab->getElements() as $layoutElement) {
                $exclude[] = match (true) {
                    $layoutElement instanceof VariantsField => 'variants',
                    $layoutElement instanceof OptionsField => 'options',
                    $layoutElement instanceof MetafieldsField => 'metafields',
                    default => null,
                };
            }
        }

        return [
            ...parent::getMetadataGroups(),
            ProductHelper::metadata($this, array_filter($exclude)),
        ];
    }

    #[Override]
    protected function previewTargets(): array
    {
        if ($uriFormat = app(Settings::class)->uriFormat) {
            return [[
                'label' => t('Primary {type} page', [
                    'type' => static::lowerDisplayName(),
                ]),
                'urlFormat' => $uriFormat,
            ]];
        }

        return [];
    }

    #[Override]
    protected function route(): array|string|null
    {
        if (!$this->previewing && $this->getStatus() !== self::STATUS_LIVE) {
            return null;
        }

        $settings = app(Settings::class);

        if (!$settings->uriFormat) {
            return null;
        }

        return [
            'templates/render', [
                'template' => $settings->template,
                'variables' => [
                    'product' => $this,
                ],
            ],
        ];
    }

    #[Override]
    protected function uiLabel(): ?string
    {
        if (!isset($this->title) || trim($this->title) === '') {
            return t('Untitled product', category: 'shopify');
        }

        return null;
    }

    #[Override]
    protected function cpEditUrl(): ?string
    {
        return Url::cpUrl(sprintf('shopify/products/%s', $this->getCanonicalId()));
    }

    #[Override]
    protected function searchKeywords(string $attribute): string
    {
        return match ($attribute) {
            'options' => Str::toString(collect($this->getOptions())
                ->map(fn($option) => !empty($option['values']) ? Str::toString($option['values']) : '')
                ->all()),
            default => parent::searchKeywords($attribute),
        };
    }

    #[Override]
    protected function attributeHtml(string $attribute): string
    {
        return match ($attribute) {
            'shopifyEdit' => Html::a('', $this->getShopifyEditUrl(), ['target' => '_blank', 'data' => ['icon' => 'external']]),
            'shopifyStatus' => $this->getShopifyStatusHtml(),
            'shopifyId' => (string)$this->shopifyId,
            'options' => collect($this->getOptions())
                ->map(fn(array $option) => Html::tag('span', Html::encode($option['name']), [
                    'title' => $option['name'] . ' option values: ' . collect($option['values'])->join(', '),
                ]))
                ->join(',&nbsp;'),
            'tags' => collect($this->getTags())
                ->map(fn(string $tag) => Html::tag('div', Html::encode($tag), [
                    'style' => 'margin-bottom: 2px;',
                    'class' => 'token',
                ]))
                ->join('&nbsp;'),
            'variants' => $this->getVariants()
                ->map(fn(Variant $variant) => Html::encode(Str::title($variant->title)))
                ->join(',&nbsp;'),
            'templateSuffix' => Html::tag('code', Html::encode((string)$this->templateSuffix)),
            default => (string)parent::attributeHtml($attribute),
        };
    }

    #[Override]
    public function beforeSave(bool $isNew): bool
    {
        // Keep the slug and handle the same
        if ($this->handle !== $this->slug) {
            $this->slug = $this->handle;
        }

        return parent::beforeSave($isNew);
    }

    #[Override]
    public function afterSave(bool $isNew): void
    {
        if ($isNew) {
            $record = new ProductRecord();
            $record->id = $this->id;
        } else {
            $record = ProductRecord::query()->find($this->id)
                ?? throw new RuntimeException("Invalid product ID: $this->id");
        }

        $record->shopifyId = $this->shopifyId;
        $record->shopifyGid = $this->shopifyGid;

        // Use the same dates as the element, as they're set when it's saved (and resaved)
        $record->dateUpdated = $this->dateUpdated;
        $record->dateCreated = $this->dateCreated;

        $record->save();

        parent::afterSave($isNew);
    }

    #[Override]
    public function afterDelete(): void
    {
        // Remove the product's Shopify data
        if ($this->shopifyGid && $this->getIsCanonical()) {
            app(Products::class)->deleteShopifyDataByShopifyGid($this->shopifyGid);
        }

        parent::afterDelete();
    }
}
