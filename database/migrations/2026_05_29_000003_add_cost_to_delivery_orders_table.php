<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('delivery_orders', function (Blueprint $table) {
            // Biaya vendor / HPP langsung
            if (!Schema::hasColumn('delivery_orders', 'cost')) {
                $table->decimal('cost', 15, 0)->default(0)->comment('Biaya vendor / HPP');
            }
            // Biaya operasional lain (trucking, handling, dll)
            if (!Schema::hasColumn('delivery_orders', 'other_cost')) {
                $table->decimal('other_cost', 15, 0)->default(0)->after('cost')
                    ->comment('Biaya operasional lain');
            }
        });
    }

    public function down(): void
    {
        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->dropColumn(['cost', 'other_cost']);
        });
    }
};
