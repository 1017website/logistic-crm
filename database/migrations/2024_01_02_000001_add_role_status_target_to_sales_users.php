<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_users', function (Blueprint $table) {
            $table->string('role')->default('Sales Executive')->after('position');
            $table->enum('status', ['Active', 'Non-Active'])->default('Active')->after('role');
            $table->bigInteger('target')->default(500000000)->after('status'); // target bulanan dalam IDR
        });
    }

    public function down(): void
    {
        Schema::table('sales_users', function (Blueprint $table) {
            $table->dropColumn(['role', 'status', 'target']);
        });
    }
};
