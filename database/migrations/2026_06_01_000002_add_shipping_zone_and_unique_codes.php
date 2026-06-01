<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Revisi lanjutan:
 * 1. Tambah 'shipping_zone' (zona pengiriman) di lead_products & customer_products.
 * 2. Pastikan unique code di semua modul:
 *    - vendors.vendor_code (auto VND-YYYY-0001)
 *    - customers.customer_code (auto CUST-YYYY-0001)
 *    Lead sudah punya lead_code, DO sudah punya do_number (keduanya unique).
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Zona pengiriman
        foreach (['lead_products', 'customer_products'] as $table) {
            if (Schema::hasTable($table) && !Schema::hasColumn($table, 'shipping_zone')) {
                Schema::table($table, function (Blueprint $t) use ($table) {
                    $col = $t->string('shipping_zone')->nullable();
                    if (Schema::hasColumn($table, 'unit')) $col->after('unit');
                });
            }
        }

        // 2. Unique code: vendors
        if (Schema::hasTable('vendors') && !Schema::hasColumn('vendors', 'vendor_code')) {
            Schema::table('vendors', function (Blueprint $t) {
                $t->string('vendor_code')->nullable()->after('id');
            });
            // Backfill kode untuk data lama
            $year = date('Y');
            $seq = 1;
            foreach (DB::table('vendors')->orderBy('id')->get() as $v) {
                DB::table('vendors')->where('id', $v->id)->update([
                    'vendor_code' => 'VND-' . $year . '-' . str_pad($seq++, 4, '0', STR_PAD_LEFT),
                ]);
            }
            // Unique index (nullable tetap boleh unique di MySQL utk multiple NULL)
            Schema::table('vendors', fn(Blueprint $t) => $t->unique('vendor_code'));
        }

        // 3. Unique code: customers
        if (Schema::hasTable('customers') && !Schema::hasColumn('customers', 'customer_code')) {
            Schema::table('customers', function (Blueprint $t) {
                $t->string('customer_code')->nullable()->after('id');
            });
            $year = date('Y');
            $seq = 1;
            foreach (DB::table('customers')->orderBy('id')->get() as $c) {
                DB::table('customers')->where('id', $c->id)->update([
                    'customer_code' => 'CUST-' . $year . '-' . str_pad($seq++, 4, '0', STR_PAD_LEFT),
                ]);
            }
            Schema::table('customers', fn(Blueprint $t) => $t->unique('customer_code'));
        }
    }

    public function down(): void
    {
        foreach (['lead_products', 'customer_products'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'shipping_zone')) {
                Schema::table($table, fn(Blueprint $t) => $t->dropColumn('shipping_zone'));
            }
        }
        if (Schema::hasTable('vendors') && Schema::hasColumn('vendors', 'vendor_code')) {
            Schema::table('vendors', function (Blueprint $t) {
                $t->dropUnique(['vendor_code']);
                $t->dropColumn('vendor_code');
            });
        }
        if (Schema::hasTable('customers') && Schema::hasColumn('customers', 'customer_code')) {
            Schema::table('customers', function (Blueprint $t) {
                $t->dropUnique(['customer_code']);
                $t->dropColumn('customer_code');
            });
        }
    }
};
