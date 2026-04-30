<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabel users sudah ada dari Laravel default, cukup tambahkan kolom yang kurang
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'role')) {
                $table->enum('role', ['Admin', 'Sales Manager', 'Sales Executive'])
                      ->default('Sales Executive')->after('email');
            }
            if (!Schema::hasColumn('users', 'status')) {
                $table->enum('status', ['Active', 'Non-Active'])
                      ->default('Active')->after('role');
            }
            if (!Schema::hasColumn('users', 'target')) {
                $table->bigInteger('target')->default(500000000)->after('status');
            }
            if (!Schema::hasColumn('users', 'phone')) {
                $table->string('phone')->nullable()->after('target');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'status', 'target', 'phone']);
        });
    }
};
