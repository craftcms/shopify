<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\models;

use Craft;
use craft\base\Model;
use craft\behaviors\FieldLayoutBehavior;
use craft\elements\Entry;
use craft\helpers\UrlHelper;
use craft\models\FieldLayout;
use craft\shopify\elements\Product;
use yii\base\InvalidConfigException;

/**
 * Shopify Store model.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 *
 * @mixin FieldLayoutBehavior
 * @property-read array $config
 * @property-read FieldLayout $productFieldLayout
 */
class Store extends Model
{
    /**
     * @var string
     */
    public string $name = '';

    /**
     * @var string
     */
    public string $apiKey = '';

    /**
     * @var string
     */
    public string $apiSecretKey = '';

    /**
     * @var string
     */
    public string $accessToken = '';

    /**
     * @var string
     */
    public string $hostName = '';

    /**
     * @var string
     */
    public string $uriFormat = '';

    /**
     * @var string
     */
    public string $template = '';

    /**
     * @var int|null Field layout ID
     */
    public ?int $productFieldLayoutId = null;

    /**
     * @var FieldLayout|null Field layout
     */
    private ?FieldLayout $_productFieldLayout = null;

    /**
     * @var string|null UID
     */
    public ?string $uid = null;

    /**
     * @inheritdoc
     */
    protected function defineBehaviors(): array
    {
        $behaviors = parent::behaviors();

        $behaviors['productFieldLayout'] = [
            'class' => FieldLayoutBehavior::class,
            'elementType' => Product::class,
            'idAttribute' => 'productFieldLayoutId',
        ];

        return $behaviors;
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['apiSecretKey', 'apiKey', 'accessToken', 'hostName'], 'required'],
            ['productFieldLayout', 'validateProductFieldLayout'],
        ];
    }

    /**
     * Validate the field layout to make sure no fields with reserved words are used.
     *
     * @since 4.1
     */
    public function validateProductFieldLayout(): void
    {
        /** @var FieldLayoutBehavior $behavior */
        $behavior = $this->getBehavior('productFieldLayout');
        $productFieldLayout = $behavior->getFieldLayout();

        $productFieldLayout->reservedFieldHandles = [
            'shopifyId',
            'bodyHtml',
            'createdAt',
            'handle',
            'images',
            'options',
            'productType',
            'publishedAt',
            'publishedScope',
            'tags',
            'shopifyStatus',
            'templateSuffix',
            'updatedAt',
            'variants',
            'vendor',
            'metaFields',
        ];

        if (!$productFieldLayout->validate()) {
            $this->addModelErrors($productFieldLayout, 'productFieldLayout');
        }
    }

    /**
     * Returns the field layout config for this email.
     *
     * @since 3.1
     */
    public function getConfig(): array
    {
        $config = [
            'name' => $this->name,
            'hostName' => $this->hostName,
            'apiKey' => $this->apiKey,
            'apiSecretKey' => $this->apiSecretKey,
            'accessToken' => $this->accessToken,
            'uriFormat' => $this->uriFormat,
            'template' => $this->template,
        ];

        $fieldLayout = $this->getProductFieldLayout();

        if ($fieldLayoutConfig = $fieldLayout->getConfig()) {
            $config['fieldLayouts'] = [
                $fieldLayout->uid => $fieldLayoutConfig,
            ];
        }

        return $config;
    }

    /**
     * Returns the product's field layout.
     *
     * @return FieldLayout
     * @throws InvalidConfigException if the configured field layout ID is invalid
     */
    public function getProductFieldLayout(): FieldLayout
    {
        /** @var FieldLayoutBehavior $behavior */
        $behavior = $this->getBehavior('productFieldLayout');
        $this->_productFieldLayout = $behavior->getFieldLayout();

        return $this->_productFieldLayout;
    }


    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        return [
            'apiKey' => Craft::t('shopify', 'Shopify API Key'),
            'apiSecretKey' => Craft::t('shopify', 'Shopify API Secret Key'),
            'accessToken' => Craft::t('shopify', 'Shopify Access Token'),
            'hostName' => Craft::t('shopify', 'Shopify Host Name'),
            'uriFormat' => Craft::t('shopify', 'Product URI format'),
            'template' => Craft::t('shopify', 'Product Template'),
        ];
    }

    /**
     * @param mixed $fieldLayout
     * @return void
     */
    public function setProductFieldLayout(mixed $fieldLayout): void
    {
        $this->_productFieldLayout = $fieldLayout;
    }

    /**
     * @return string
     */
    public function getWebhookUrl(): string
    {
        return UrlHelper::actionUrl('shopify/webhook/handle');
    }
}
