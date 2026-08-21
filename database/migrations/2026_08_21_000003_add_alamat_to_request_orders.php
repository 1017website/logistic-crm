<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('request_orders') || Schema::hasColumn('request_orders', 'alamat')) {
            return;
        }

        Schema::table('request_orders', function (Blueprint $table) {
            $table->text('alamat')->nullable()->after('kota');
        });

        DB::table('request_orders')
            ->select(['id', 'kecamatan', 'kelurahan'])
            ->orderBy('id')
            ->chunkById(100, function ($orders) {
                foreach ($orders as $order) {
                    $alamat = collect([$order->kecamatan, $order->kelurahan])
                        ->filter(fn ($value) => filled($value))
                        ->implode(', ');

                    if ($alamat !== '') {
                        DB::table('request_orders')->where('id', $order->id)->update(['alamat' => $alamat]);
                    }
                }
            });
    }

    public function down(): void
    {
        if (Schema::hasTable('request_orders') && Schema::hasColumn('request_orders', 'alamat')) {
            Schema::table('request_orders', function (Blueprint $table) {
                $table->dropColumn('alamat');
            });
        }
    }
};
