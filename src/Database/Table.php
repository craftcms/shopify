<?php

declare(strict_types=1);

namespace CraftCms\Shopify\Database;

/**
 * Shopify’s database table names, without the table prefix.
 */
abstract class Table
{
    public const string ACCESS_TOKENS = 'shopify_accesstokens';

    public const string BULK_OPERATIONS = 'shopify_bulkoperations';

    public const string DATA = 'shopify_data';

    public const string PRODUCTS = 'shopify_products';
}
