<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('order_job_details')) {
            return;
        }

        if (! Schema::hasColumn('order_job_details', 'created_by')) {
            Schema::table('order_job_details', function (Blueprint $table) {
                $table->foreignId('created_by')->nullable()->after('catatan')
                    ->constrained('users', 'id', 'ojd_created_by_fk')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('order_job_details', 'updated_by')) {
            Schema::table('order_job_details', function (Blueprint $table) {
                $table->foreignId('updated_by')->nullable()->after('created_by')
                    ->constrained('users', 'id', 'ojd_updated_by_fk')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('order_job_details')) {
            return;
        }

        if (Schema::hasColumn('order_job_details', 'updated_by')) {
            Schema::table('order_job_details', function (Blueprint $table) {
                $table->dropForeign('ojd_updated_by_fk');
                $table->dropColumn('updated_by');
            });
        }

        if (Schema::hasColumn('order_job_details', 'created_by')) {
            Schema::table('order_job_details', function (Blueprint $table) {
                $table->dropForeign('ojd_created_by_fk');
                $table->dropColumn('created_by');
            });
        }
    }
};
