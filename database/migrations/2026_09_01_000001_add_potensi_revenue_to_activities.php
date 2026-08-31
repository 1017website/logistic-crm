<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Simpan potensi revenue yang dicatat pada sebuah activity.
     *
     * Pola ini mengikuti kolom pipeline_stage yang juga disimpan di activity
     * sekaligus diteruskan ke lead — sehingga timeline menyimpan riwayat nilai
     * yang dilaporkan, bukan hanya nilai terakhir di lead.
     *
     * Null berarti activity tersebut tidak mengubah potensi revenue.
     */
    public function up(): void
    {
        if (!Schema::hasTable('activities') || Schema::hasColumn('activities', 'potensi_revenue')) {
            return;
        }

        Schema::table('activities', function (Blueprint $table): void {
            $table->decimal('potensi_revenue', 15, 0)->nullable()->after('pipeline_stage');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('activities') || !Schema::hasColumn('activities', 'potensi_revenue')) {
            return;
        }

        Schema::table('activities', function (Blueprint $table): void {
            $table->dropColumn('potensi_revenue');
        });
    }
};
