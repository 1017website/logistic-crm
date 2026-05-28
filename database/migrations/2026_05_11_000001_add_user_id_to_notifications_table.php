<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tambah kolom yang belum ada (idempotent)
        Schema::table('notifications', function (Blueprint $table) {
            if (!Schema::hasColumn('notifications', 'user_id')) {
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete()->after('id');
            }
            if (!Schema::hasColumn('notifications', 'icon')) {
                $table->string('icon')->default('bell')->after('url');
            }
            if (!Schema::hasColumn('notifications', 'icon_color')) {
                $table->string('icon_color')->default('#3b82f6')->after('icon');
            }
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn(['icon', 'icon_color']);
        });
    }
};
