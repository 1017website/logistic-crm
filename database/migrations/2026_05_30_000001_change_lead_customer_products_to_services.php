<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['lead_products', 'customer_products'] as $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (!Schema::hasColumn($tableName, 'service_name')) {
                    $table->string('service_name')->nullable()->after('id');
                }
            });

            if (Schema::hasColumn($tableName, 'product_name')) {
                DB::table($tableName)
                    ->whereNull('service_name')
                    ->update(['service_name' => DB::raw('product_name')]);
            }
        }
    }

    public function down(): void
    {
        foreach (['lead_products', 'customer_products'] as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'service_name')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn('service_name');
                });
            }
        }
    }
};
