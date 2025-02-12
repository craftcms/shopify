<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\models;

use craft\base\Model;
use DateTime;

/**
 * Bulk Operations model.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 6.0.0
 */
class BulkOperation extends Model
{
    /**
     * @var int|null
     */
    public ?int $id = null;

    /**
     * @var string|null The Shopify ID of the bulk operation.
     */
    public ?string $shopifyId = null;

    /**
     * @var string|null The URL of the bulk operation data.
     */
    public ?string $url = null;

    /**
     * @var int|null
     */
    public ?int $objectCount = null;

    /**
     * @var string
     */
    public string $status = \craft\shopify\records\BulkOperation::STATUS_QUEUED;

    /**
     * @var string|null
     */
    public ?string $shopifyStatus = null;

    /**
     * @var string|null
     */
    public ?string $query = null;

    /**
     * @var DateTime|null
     */
    public ?DateTime $dateCreated = null;

    /**
     * @var DateTime|null
     */
    public ?DateTime $dateUpdated = null;

    protected function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [['id', 'objectCount'], 'number', 'integerOnly' => true];
        $rules[] = [['shopifyId', 'url'], 'string'];
        $rules[] = [['id', 'shopifyId', 'url', 'objectCount', 'status', 'shopifyStatus', 'query', 'dateCreated', 'dateUpdated'], 'safe'];

        return $rules;
    }
}
