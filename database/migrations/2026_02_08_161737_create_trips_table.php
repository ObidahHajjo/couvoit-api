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
        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            $table->dateTime('departure_time');
            $table->dateTime('arrival_time');
            $table->decimal('distance_km', 8, 2);
            $table->smallInteger('available_seats');
            $table->boolean('smoking_allowed')->default(false);

            $table->foreignId('departure_address_id')->constrained('addresses');
            $table->foreignId('arrival_address_id')->constrained('addresses');
            $table->foreignId('person_id')->constrained('persons')->cascadeOnDelete();
            $table->softDeletes();
            $table->unique(['departure_time', 'person_id']);
        });

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE trips ADD CONSTRAINT chk_available_seats CHECK (available_seats >= 0);');
            DB::statement('ALTER TABLE trips ADD CONSTRAINT chk_distance_km CHECK (distance_km > 0);');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trips');
    }
};
