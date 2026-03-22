<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('models', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->smallInteger('seats');
            $table->foreignId('brand_id')->constrained();
            $table->foreignId('type_id')->constrained();
            $table->unique(['name', 'brand_id']);
        });

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE models ADD CONSTRAINT chk_seats_number CHECK (seats > 0 AND seats <= 9);');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('models');
    }
};
