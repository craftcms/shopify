<?php

declare(strict_types=1);

use CraftCms\Cms\Database\Migration;
use CraftCms\Shopify\Database\Table;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Craft 6 encrypts the access token with Laravel’s encrypter, whose output is longer than 255 characters.
 */
return new class() extends Migration {
    public function up(): void
    {
        Schema::table(Table::ACCESS_TOKENS, function(Blueprint $table) {
            $table->text('accessToken')->nullable()->change();
        });
    }
};
