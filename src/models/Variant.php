<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\models;

use craft\base\Model;
use craft\helpers\Json;
use craft\shopify\helpers\Metafield as MetafieldHelper;
use craft\shopify\Plugin;
use DateTime;
use yii\base\InvalidConfigException;

/**
 * Variant model.
 *
 * @property-read string $shopifyGid
 * @property-read string $shopifyId
 * @property-read string $title
 * @property-read string $sku
 * @property-read string $price
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 7.0.0
 */
class Variant extends Model
{
    /**
     * @var int|null
     */
    public ?int $id = null;

    /**
     * @var string|null The Shopify GID of the variant (e.g. "gid://shopify/ProductVariant/123456789").
     */
    public ?string $shopifyGid = null;

    /**
     * @var string|null The numeric Shopify ID of the variant (last segment of the GID).
     */
    public ?string $shopifyId = null;

    /**
     * @var string|null
     */
    public ?string $type = null;

    /**
     * @var string|null
     */
    public ?string $parentId = null;

    /**
     * @var array|null
     * @see getData()
     * @see setData()
     */
    private ?array $_data = null;

    /**
     * @var array|null
     * @see getMetafields()
     * @see setMetafields()
     */
    private ?array $_metaFields = null;

    /**
     * @var DateTime|null
     */
    public ?DateTime $dateCreated = null;

    /**
     * @var DateTime|null
     */
    public ?DateTime $dateUpdated = null;

    /**
     * @var string|null
     */
    public ?string $uid = null;


    public function __call($name, $params)
    {
        if (array_key_exists($name, $this->_data)) {
            return $this->_data[$name];
        }

        return parent::__call($name, $params);
    }

    /**
     * @inheritdoc
     */
    public function __get($name)
    {
        if (array_key_exists($name, $this->_data)) {
            return $this->_data[$name];
        }

        return parent::__get($name);
    }

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [['id', 'shopifyGid', 'shopifyId', 'type', 'parentId', 'data', 'dateCreated', 'dateUpdated', 'uid'], 'safe'];

        return $rules;
    }

    /**
     * @inheritdoc
     */
    public function attributes()
    {
        $names = parent::attributes();
        $names[] = 'data';
        $names[] = 'metafields';

        return $names;
    }

    /**
     * @param string|array|null $data
     * @return void
     */
    public function setData(string|array|null $data): void
    {
        if (is_string($data)) {
            $data = Json::decodeIfJson($data);
        }

        $this->_data = $data;
    }

    /**
     * @return array
     */
    public function getData(): array
    {
        return $this->_data;
    }

    /**
     * @param string|array $value A list-shaped array of `{key, value}` objects, or a JSON-encoded string of the same.
     * @return void
     * @throws \InvalidArgumentException if the value is not a list-shaped array or JSON string of one.
     */
    public function setMetafields(string|array $value): void
    {
        if (is_string($value)) {
            $value = Json::decodeIfJson($value);
        }

        if (!is_array($value) || !array_is_list($value)) {
            throw new \InvalidArgumentException('setMetafields() expects a list-shaped array of {key, value} objects or a JSON-encoded string of the same.');
        }

        $this->_metaFields = MetafieldHelper::normalizeToMap($value);
    }

    /**
     * @return array
     * @throws InvalidConfigException
     */
    public function getMetafields(): array
    {
        if (!$this->shopifyGid) {
            return [];
        }

        if ($this->_metaFields !== null) {
            return $this->_metaFields;
        }

        $data = Plugin::getInstance()->getApi()->getShopifyDataByType('Metafield', $this->shopifyGid);

        $this->setMetafields($data->all());

        return $this->_metaFields ?? [];
    }
}
