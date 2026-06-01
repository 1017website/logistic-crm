<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Revisi:
 * 1. Master "Service Type" yang bisa dikelola (tabel service_types) + seed default 11 data.
 * 2. Tambah kolom 'tonnage' (tonase) sebagai field tambahan terpisah di:
 *    - delivery_order_items, lead_products, customer_products, vendor_services
 *    (qty tetap dipakai untuk perhitungan Gross Profit).
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Master Service Type ──────────────────────────
        if (!Schema::hasTable('service_types')) {
            Schema::create('service_types', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }

        // Seed default (idempotent — hanya isi jika tabel kosong)
        if (Schema::hasTable('service_types') && DB::table('service_types')->count() === 0) {
            $defaults = [
                'Liner',
                'Internasional Freight Forwarding',
                'Domestic Freight Forwarding door to door',
                'Trucking trailer',
                'Trucking non trailer',
                'Forklift',
                'Alat berat (heavy duty)',
                'Ware house',
                'Genset',
                'Dump truck',
                'PPJK',
            ];
            $now = now();
            $rows = [];
            foreach ($defaults as $i => $name) {
                $rows[] = [
                    'name'       => $name,
                    'is_active'  => true,
                    'sort_order' => $i + 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            DB::table('service_types')->insert($rows);
        }

        // ── 2. Kolom tonnage (tonase) ───────────────────────
        $this->addTonnage('delivery_order_items', 'qty');
        $this->addTonnage('lead_products', 'qty');
        $this->addTonnage('customer_products', 'qty');
        $this->addTonnage('vendor_services', 'unit');
    }

    private function addTonnage(string $table, string $after): void
    {
        if (Schema::hasTable($table) && !Schema::hasColumn($table, 'tonnage')) {
            Schema::table($table, function (Blueprint $t) use ($after, $table) {
                $col = $t->decimal('tonnage', 15, 3)->nullable();
                if (Schema::hasColumn($table, $after)) {
                    $col->after($after);
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['delivery_order_items', 'lead_products', 'customer_products', 'vendor_services'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'tonnage')) {
                Schema::table($table, fn(Blueprint $t) => $t->dropColumn('tonnage'));
            }
        }
        Schema::dropIfExists('service_types');
    }
};
