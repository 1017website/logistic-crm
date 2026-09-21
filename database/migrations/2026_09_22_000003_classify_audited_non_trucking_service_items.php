<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('request_order_items')
            || !Schema::hasColumn('request_order_items', 'service_type')) {
            return;
        }

        // Nama ini berasal dari audit salinan database produksi. Semuanya biaya
        // tambahan, bukan jasa angkut utama, sehingga masuk Non-Trucking.
        DB::table('request_order_items')
            ->whereIn(DB::raw('LOWER(TRIM(service_name))'), [
                'bongkar ttl',
                'buruh',
                'empt ttl',
                'karantina',
                'kuli',
                'solar timbang',
                'timbang',
            ])
            ->update(['service_type' => 'NTR']);
    }

    public function down(): void
    {
        // Tidak mengembalikan TR karena pilihan tipe dapat sudah dikoreksi user.
    }
};
