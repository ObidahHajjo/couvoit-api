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
        Schema::create('persons', function (Blueprint $table) {
            $table->id();
            $table->string('email')->index();           // optional cache/denormalization
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('pseudo')->nullable()->unique();
            $table->string('phone', 15)->nullable();
            $table->boolean('is_active')->default(true);

            $table->uuid('supabase_user_id')->unique();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('car_id')->nullable()->unique()->constrained()->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('persons', function (Blueprint $table) {
            Schema::dropIfExists('persons');
        });
    }
};
