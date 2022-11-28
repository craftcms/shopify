<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\db;

/**
 * This class provides public constants for defining Shopify’s database table names. Do not use these in migrations.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 */
abstract class Table
{
    public const PRODUCTDATA = '{{%shopify_productdata}}';
    public const PRODUCTS = '{{%shopify_products}}';
    public const STORES = '{{%shopify_stores}}';
}
