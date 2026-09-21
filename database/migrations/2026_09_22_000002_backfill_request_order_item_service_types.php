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

        DB::table('request_order_items')
            ->whereNull('service_type')
            ->where(function ($query) {
                $query->whereRaw('LOWER(service_name) LIKE ?', ['%non trucking%'])
                    ->orWhereRaw('LOWER(service_name) LIKE ?', ['%non-trucking%'])
                    ->orWhereRaw('LOWER(service_name) LIKE ?', ['%nontrucking%'])
                    ->orWhereRaw('TRIM(LOWER(service_name)) = ?', ['ntr'])
                    ->orWhereRaw('LOWER(service_name) LIKE ?', ['ntr %'])
                    ->orWhereRaw('LOWER(service_name) LIKE ?', ['% (ntr)%']);
            })
            ->update(['service_type' => 'NTR']);

        DB::table('request_order_items')
            ->whereNull('service_type')
            ->update(['service_type' => 'TR']);
    }

    public function down(): void
    {
        // Klasifikasi lama tidak dapat dibedakan dari pilihan user setelah migrasi.
    }
};
