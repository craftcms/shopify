<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\enums;

use Craft;
use craft\enums\Color;
use craft\helpers\Cp;

/**
 * Bulk Operation Status enum
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 6.0.0
 */
enum BulkOperationStatus: string
{
    case Queued = 'queued';
    case Created = 'created';
    case Processing = 'processing';
    case Completed = 'completed';

    /**
     * @return string
     */
    public function statusAsLabel(): string
    {
        return match ($this) {
            self::Queued => Craft::t('shopify', 'Queued'),
            self::Created => Craft::t('shopify', 'Created'),
            self::Processing => Craft::t('shopify', 'Processing'),
            self::Completed => Craft::t('shopify', 'Completed'),
        };
    }

    /**
     * @return string
     */
    public function statusLabelHtml(): string
    {
        return Cp::statusLabelHtml([
            'color' => match ($this) {
                self::Queued => Color::Gray,
                self::Created => Color::Blue,
                self::Processing => Color::Yellow,
                self::Completed => Color::Green,
            },
            'label' => $this->statusAsLabel(),
        ]);
    }
}
