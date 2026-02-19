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
        Schema::table('trips', function (Blueprint $table) {
            if (!Schema::hasColumn('trips', 'created_at')) {
                $table->timestamps();
            }

            if (!Schema::hasColumn('trips', 'deleted_at')) {
                $table->softDeletes();
            }

            if (!Schema::hasColumn('trips', 'created_by')) {
                $table->foreignId('created_by')->nullable()->constrained('persons')->nullOnDelete();
            }
            if (!Schema::hasColumn('trips', 'updated_by')) {
                $table->foreignId('updated_by')->nullable()->constrained('persons')->nullOnDelete();
            }
            if (!Schema::hasColumn('trips', 'deleted_by')) {
                $table->foreignId('deleted_by')->nullable()->constrained('persons')->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            if (Schema::hasColumn('trips', 'created_by')) $table->dropConstrainedForeignId('created_by');
            if (Schema::hasColumn('trips', 'updated_by')) $table->dropConstrainedForeignId('updated_by');
            if (Schema::hasColumn('trips', 'deleted_by')) $table->dropConstrainedForeignId('deleted_by');
            if (Schema::hasColumn('trips', 'deleted_at')) $table->dropSoftDeletes();
        });
    }
};
