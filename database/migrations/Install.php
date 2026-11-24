<?php

declare(strict_types=1);

use craft\shopify\elements\Product;
use craft\shopify\enums\BulkOperationStatus;
use craft\shopify\records\BulkOperation;
use CraftCms\Cms\Database\Expressions\JsonExtract;
use CraftCms\Cms\Database\Migration;
use CraftCms\Cms\Database\Table as CraftTable;
use CraftCms\Cms\Support\Facades\ProjectConfig;
use CraftCms\Shopify\Database\Table;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        $this->createTables();
        $this->createIndexes();
        $this->addForeignKeys();
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();

        foreach ($this->allTableNames() as $table) {
            Schema::dropIfExists($table);
        }

        Schema::enableForeignKeyConstraints();

        DB::table(CraftTable::FIELDLAYOUTS)->where('type', Product::class)->delete();

        ProjectConfig::remove('shopify');
    }

    private function createTables(): void
    {
        $this->archiveTableIfExists(Table::ACCESS_TOKENS);
        Schema::create(Table::ACCESS_TOKENS, function(Blueprint $table) {
            $table->integer('id', true);
            $table->string('accessToken')->nullable();
            $table->dateTime('dateCreated');
            $table->dateTime('dateUpdated');
            $table->char('uid', 36)->default('0');
        });

        $this->archiveTableIfExists(Table::PRODUCTS);
        Schema::create(Table::PRODUCTS, function(Blueprint $table) {
            $table->integer('id')->primary();
            $table->string('shopifyId')->nullable();
            $table->string('shopifyGid')->nullable();
            $table->dateTime('dateCreated');
            $table->dateTime('dateUpdated');
            $table->char('uid', 36)->default('0');
        });

        $this->archiveTableIfExists(Table::DATA);
        Schema::create(Table::DATA, function(Blueprint $table) {
            $table->integer('id', true);
            $table->string('shopifyGid')->nullable();
            $table->string('type')->nullable();
            $table->jsonb('data')->nullable();
            $table->string('parentId')->nullable();
            $table->integer('position')->nullable();
            $table->dateTime('dateCreated');
            $table->dateTime('dateUpdated');
            $table->char('uid', 36)->default('0');

            // Generated columns, so product data can be queried and sorted without parsing the JSON
            foreach ([
                'createdAt' => 'createdAt',
                'handle' => 'handle',
                'productType' => 'productType',
                'publishedAt' => 'publishedAt',
                'shopifyStatus' => 'status',
                'templateSuffix' => 'templateSuffix',
                'title' => 'title',
                'updatedAt' => 'updatedAt',
                'vendor' => 'vendor',
            ] as $column => $key) {
                $table->string($column)->nullable()->storedAs($this->jsonValue($key));
            }

            $table->text('tags')->nullable()->storedAs($this->jsonValue('tags'));
            $table->text('options')->nullable()->storedAs($this->jsonValue('options'));
            $table->integer('totalInventory')->nullable()->storedAs($this->jsonInteger('totalInventory'));
            $table->string('shopifyId')->nullable()->storedAs($this->gidToId('shopifyGid'));
        });

        $this->archiveTableIfExists(Table::BULK_OPERATIONS);
        Schema::create(Table::BULK_OPERATIONS, function(Blueprint $table) {
            $table->integer('id', true);
            $table->string('shopifyGid')->nullable();
            $table->text('url')->nullable();
            $table->integer('objectCount')->nullable();
            $table->text('query')->nullable();
            $table->enum('status', array_column(BulkOperationStatus::cases(), 'value'))->default(BulkOperationStatus::Queued->value);
            $table->string('shopifyStatus')->nullable();
            $table->string('clearData')->default(BulkOperation::CLEAR_DATA_NONE);
            $table->dateTime('dateCreated');
            $table->dateTime('dateUpdated');
            $table->char('uid', 36)->default('0');
        });
    }

    private function createIndexes(): void
    {
        Schema::createIndex(Table::PRODUCTS, ['shopifyId']);
        Schema::createIndex(Table::PRODUCTS, ['shopifyGid']);
        Schema::createIndex(Table::DATA, ['shopifyGid']);
        Schema::createIndex(Table::DATA, ['parentId']);
    }

    private function addForeignKeys(): void
    {
        Schema::table(Table::PRODUCTS, fn(Blueprint $table) => $table->foreign('id')->references('id')->on(CraftTable::ELEMENTS)->cascadeOnDelete()->cascadeOnUpdate());
    }

    /**
     * Returns the SQL that extracts a top-level key from the `data` column as text.
     */
    private function jsonValue(string $key): string
    {
        return new JsonExtract('data', [$key])->getValue(DB::connection()->getQueryGrammar());
    }

    private function jsonInteger(string $key): string
    {
        $value = $this->jsonValue($key);

        return match (DB::connection()->getDriverName()) {
            'pgsql' => "($value)::integer",
            'sqlite' => "CAST($value AS INTEGER)",
            default => "CAST($value AS SIGNED)",
        };
    }

    /**
     * Returns the SQL that extracts the numeric ID from the end of a Shopify GID, such as `7136060145715` from
     * `gid://shopify/Product/7136060145715`.
     */
    private function gidToId(string $column): string
    {
        $column = DB::connection()->getQueryGrammar()->wrap($column);

        return match (DB::connection()->getDriverName()) {
            'pgsql' => "regexp_replace($column, '^.*/', '')",
            'sqlite' => "replace($column, rtrim($column, replace($column, '/', '')), '')",
            default => "SUBSTRING_INDEX($column, '/', -1)",
        };
    }

    /**
     * @return string[]
     */
    private function allTableNames(): array
    {
        return array_values(new ReflectionClass(Table::class)->getConstants());
    }
};
