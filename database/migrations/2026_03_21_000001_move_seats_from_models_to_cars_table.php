<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cars', function (Blueprint $table) {
            $table->smallInteger('seats')->nullable()->after('license_plate');
        });

        DB::statement('UPDATE cars SET seats = (SELECT models.seats FROM models WHERE models.id = cars.model_id)');

        Schema::table('cars', function (Blueprint $table) {
            $table->smallInteger('seats')->nullable(false)->change();
        });

        $driver = Schema::getConnection()->getDriverName();

        if ($driver !== 'sqlite') {
            DB::statement('ALTER TABLE cars ADD CONSTRAINT chk_car_seats_number CHECK (seats > 0 AND seats <= 9)');
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE models DROP CONSTRAINT IF EXISTS chk_seats_number');
        } elseif ($driver === 'mysql') {
            DB::statement('ALTER TABLE models DROP CHECK chk_seats_number');
        }

        Schema::table('models', function (Blueprint $table) {
            $table->dropColumn('seats');
        });
    }

    public function down(): void
    {
        Schema::table('models', function (Blueprint $table) {
            $table->smallInteger('seats')->nullable()->after('name');
        });

        DB::statement('UPDATE models SET seats = COALESCE((SELECT cars.seats FROM cars WHERE cars.model_id = models.id LIMIT 1), 5)');

        Schema::table('models', function (Blueprint $table) {
            $table->smallInteger('seats')->nullable(false)->change();
        });

        $driver = Schema::getConnection()->getDriverName();

        if ($driver !== 'sqlite') {
            DB::statement('ALTER TABLE models ADD CONSTRAINT chk_seats_number CHECK (seats > 0 AND seats <= 9)');
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE cars DROP CONSTRAINT IF EXISTS chk_car_seats_number');
        } elseif ($driver === 'mysql') {
            DB::statement('ALTER TABLE cars DROP CHECK chk_car_seats_number');
        }

        Schema::table('cars', function (Blueprint $table) {
            $table->dropColumn('seats');
        });
    }
};
