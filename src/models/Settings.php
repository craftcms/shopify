<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\models;

use Craft;
use craft\base\Model;
use craft\helpers\UrlHelper;
use craft\shopify\elements\Product;

/**
 * Shopify Settings model.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 */
class Settings extends Model
{
    public string $apiKey = '';
    public string $apiSecretKey = '';
    public string $accessToken = '';
    public string $hostName = '';
    public string $uriFormat = '';
    public string $template = '';
    private mixed $_productFieldLayout;

    /**
     * @var bool Whether to rate limit requests to Shopify
     * @since 3.3.0
     */
    public bool $rateLimitRequests = true;

    /**
     * @var int The number of seconds to wait between requests
     * @since 3.3.0
     */
    public int $rateLimitSeconds = 1;

    public function rules(): array
    {
        return [
            [['apiSecretKey', 'apiKey', 'accessToken', 'hostName'], 'required'],
            [['rateLimitSeconds', 'rateLimitRequests'], 'safe'],
        ];
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
            'rateLimitRequests' => Craft::t('shopify', 'Rate Limit Requests'),
            'rateLimitSeconds' => Craft::t('shopify', 'Rate Limit Seconds'),
        ];
    }

    /**
     * @return \craft\models\FieldLayout|mixed
     */
    public function getProductFieldLayout()
    {
        if (!isset($this->_productFieldLayout)) {
            $this->_productFieldLayout = Craft::$app->fields->getLayoutByType(Product::class);
        }

        return $this->_productFieldLayout;
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
