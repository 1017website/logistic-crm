<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('loading_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('loading_orders', function (Blueprint $table) {
            $table->dropColumn('created_by');
        });
    }
};
