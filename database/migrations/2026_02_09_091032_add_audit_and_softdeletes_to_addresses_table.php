<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('addresses', function (Blueprint $table) {

            if (!Schema::hasColumn('addresses', 'created_at')) {
                $table->timestamps();
            }

            if (!Schema::hasColumn('addresses', 'deleted_at')) {
                $table->softDeletes();
            }

            if (!Schema::hasColumn('addresses', 'created_by')) {
                $table->foreignId('created_by')->nullable()->constrained('persons')->nullOnDelete();
            }
            if (!Schema::hasColumn('addresses', 'updated_by')) {
                $table->foreignId('updated_by')->nullable()->constrained('persons')->nullOnDelete();
            }
            if (!Schema::hasColumn('addresses', 'deleted_by')) {
                $table->foreignId('deleted_by')->nullable()->constrained('persons')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            if (Schema::hasColumn('addresses', 'created_by')) $table->dropConstrainedForeignId('created_by');
            if (Schema::hasColumn('addresses', 'updated_by')) $table->dropConstrainedForeignId('updated_by');
            if (Schema::hasColumn('addresses', 'deleted_by')) $table->dropConstrainedForeignId('deleted_by');
            if (Schema::hasColumn('addresses', 'deleted_at')) $table->dropSoftDeletes();
        });
    }
};
