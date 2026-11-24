<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\events;

use BackedEnum;
use craft\base\Event;
use Stringable;

/**
 * Event triggered while resolving query arguments for Shopify GraphQL queries.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 7.0.0
 */
class DefineGqlQueryArgumentsEvent extends Event
{
    /**
     * @var string
     */
    public string $fieldName;

    /**
     * @var array<null|scalar|array<?scalar>|Stringable|BackedEnum>
     */
    public array $arguments;
}
