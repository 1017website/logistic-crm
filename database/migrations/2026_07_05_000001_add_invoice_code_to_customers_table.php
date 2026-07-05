<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('customers') && !Schema::hasColumn('customers', 'invoice_code')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->string('invoice_code', 30)->nullable()->after('customer_code');
                $table->unique('invoice_code');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('customers') && Schema::hasColumn('customers', 'invoice_code')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropUnique(['invoice_code']);
                $table->dropColumn('invoice_code');
            });
        }
    }
};
