<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('models', function (Blueprint $table) {
            $table->string('search_key', 150)->nullable()->after('name');
            $table->unique(['brand_id', 'search_key'], 'models_brand_id_search_key_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('models', function (Blueprint $table) {
            $table->dropUnique('models_brand_id_search_key_unique');
            $table->dropColumn('search_key');
        });
    }
};
