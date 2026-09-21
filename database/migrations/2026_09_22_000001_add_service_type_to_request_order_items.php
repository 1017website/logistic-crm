<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('request_order_items')
            && !Schema::hasColumn('request_order_items', 'service_type')) {
            Schema::table('request_order_items', function (Blueprint $table) {
                $table->string('service_type', 10)->nullable()->after('service_name')
                    ->comment('TR=Trucking, NTR=Non-Trucking');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('request_order_items')
            && Schema::hasColumn('request_order_items', 'service_type')) {
            Schema::table('request_order_items', function (Blueprint $table) {
                $table->dropColumn('service_type');
            });
        }
    }
};
