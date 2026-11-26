<?php

declare(strict_types=1);

use CraftCms\Cms\Database\Migration;
use CraftCms\Cms\Database\Table as CraftTable;
use CraftCms\Cms\ProjectConfig\ProjectConfig;
use CraftCms\Shopify\Product\Conditions;
use CraftCms\Shopify\Product\Elements\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Renames the stored Shopify product element type, and its condition classes, to their Craft 6 class names.
 *
 * Based on core’s `migrate_entry_types` and `migrate_stored_condition_types` migrations.
 */
return new class() extends Migration {
    private const array ELEMENT_TYPES = [
        'craft\shopify\elements\Product' => Product::class,
    ];

    private const array TYPES = self::ELEMENT_TYPES + [
        'craft\shopify\elements\conditions\products\ProductCondition' => Conditions\ProductCondition::class,
        'craft\shopify\elements\conditions\products\HandleConditionRule' => Conditions\HandleConditionRule::class,
        'craft\shopify\elements\conditions\products\ProductTypeConditionRule' => Conditions\ProductTypeConditionRule::class,
        'craft\shopify\elements\conditions\products\ShopifyStatusConditionRule' => Conditions\ShopifyStatusConditionRule::class,
        'craft\shopify\elements\conditions\products\TagsConditionRule' => Conditions\TagsConditionRule::class,
        'craft\shopify\elements\conditions\products\TemplateSuffixConditionRule' => Conditions\TemplateSuffixConditionRule::class,
        'craft\shopify\elements\conditions\products\VendorConditionRule' => Conditions\VendorConditionRule::class,
    ];

    public function up(): void
    {
        foreach ([CraftTable::ELEMENTS, CraftTable::FIELDLAYOUTS] as $table) {
            foreach (self::ELEMENT_TYPES as $old => $new) {
                DB::table($table)->where('type', $old)->update(['type' => $new]);
            }
        }

        foreach ([CraftTable::FIELDLAYOUTS => 'config', CraftTable::FIELDS => 'settings'] as $table => $column) {
            foreach (DB::table($table)->whereNotNull($column)->lazyById() as $row) {
                $config = json_decode($row->$column, flags: JSON_THROW_ON_ERROR);
                $updated = $this->updateConfig($config);

                if ($updated !== $config) {
                    DB::table($table)->where('id', $row->id)->update([
                        $column => json_encode($updated, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION),
                    ]);
                }
            }
        }

        $projectConfig = app(ProjectConfig::class);
        $muteEvents = $projectConfig->muteEvents;
        $projectConfig->muteEvents = true;

        try {
            foreach ($projectConfig->get() ?? [] as $key => $config) {
                $updated = $this->updateConfig($config);

                if ($key === ProjectConfig::PATH_ELEMENT_SOURCES && is_array($updated)) {
                    $updated = $this->renameElementSources($updated);
                }

                if ($updated !== $config) {
                    $projectConfig->set($key, $updated);
                }
            }

            $projectConfig->saveModifiedConfigData();
        } finally {
            $projectConfig->muteEvents = $muteEvents;
        }
    }

    /**
     * Replaces old class names wherever they’re a value, including inside JSON-encoded condition configs.
     */
    private function updateConfig(mixed $config): mixed
    {
        if (is_string($config)) {
            if (isset(self::TYPES[$config])) {
                return self::TYPES[$config];
            }

            if (str_starts_with(ltrim($config), '{') && str_contains($config, 'shopify') && json_validate($config)) {
                $decoded = json_decode($config, flags: JSON_THROW_ON_ERROR);
                $updated = $this->updateConfig($decoded);

                return $updated !== $decoded ? json_encode($updated, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION) : $config;
            }

            return $config;
        }

        if (!is_array($config) && !$config instanceof stdClass) {
            return $config;
        }

        $values = array_map($this->updateConfig(...), (array)$config);

        if ($config instanceof stdClass) {
            return $values === (array)$config ? $config : (object)$values;
        }

        return $values;
    }

    /**
     * Moves the index source settings to the new class name, unless sources were already saved under it.
     *
     * @param array<string, mixed> $sources
     * @return array<string, mixed>
     */
    private function renameElementSources(array $sources): array
    {
        foreach (self::ELEMENT_TYPES as $old => $new) {
            if (!array_key_exists($old, $sources)) {
                continue;
            }

            if (array_key_exists($new, $sources) && $sources[$new] !== $sources[$old]) {
                Log::warning("Both elementSources.$old and elementSources.$new contain source settings. Craft uses elementSources.$new, and the settings at elementSources.$old were kept for you to compare and remove.");

                continue;
            }

            $sources[$new] = $sources[$old];
            unset($sources[$old]);
        }

        return $sources;
    }

    /** Products saved since the upgrade can’t be told apart from migrated ones. */
    public function down(): void
    {
    }
};
