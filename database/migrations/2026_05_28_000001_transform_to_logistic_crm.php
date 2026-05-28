<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Transform CRM Chemical (Auliachem) menjadi CRM Logistic.
 *
 * Perubahan utama:
 * 1. Rename table suppliers → vendors (+ pivot tables ikut)
 * 2. Restructure fields chemical-specific menjadi logistic-specific
 * 3. supplier_products → vendor_services (dengan tarif)
 * 4. purchase_orders → delivery_orders
 * 5. FK supplier_id → vendor_id di semua tabel referensi
 */
return new class extends Migration
{
    public function up(): void
    {
        // ──────────────────────────────────────────────
        // 1. RENAME TABLES (idempotent — skip jika target sudah ada)
        // ──────────────────────────────────────────────
        if (Schema::hasTable('suppliers') && !Schema::hasTable('vendors'))
            Schema::rename('suppliers', 'vendors');

        if (Schema::hasTable('supplier_pics') && !Schema::hasTable('vendor_pics'))
            Schema::rename('supplier_pics', 'vendor_pics');

        // supplier_pics mungkin belum dibuat (migration lama dihapus) — buat fresh
        if (!Schema::hasTable('vendor_pics')) {
            Schema::create('vendor_pics', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
                $table->string('pic_name');
                $table->string('pic_position')->nullable();
                $table->string('phone')->nullable();
                $table->string('email')->nullable();
                $table->boolean('is_primary')->default(false);
                $table->timestamps();
            });
        }

        if (Schema::hasTable('supplier_products') && !Schema::hasTable('vendor_services'))
            Schema::rename('supplier_products', 'vendor_services');

        // supplier_products mungkin belum dibuat — buat fresh
        if (!Schema::hasTable('vendor_services')) {
            Schema::create('vendor_services', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
                $table->string('service_name');
                $table->string('unit')->default('kg');
                $table->decimal('tariff', 15, 2)->default(0);
                $table->string('tariff_unit')->default('per kg');
                $table->string('route_origin')->nullable();
                $table->string('route_destination')->nullable();
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('purchase_orders') && !Schema::hasTable('delivery_orders'))
            Schema::rename('purchase_orders', 'delivery_orders');

        if (Schema::hasTable('purchase_order_items') && !Schema::hasTable('delivery_order_items'))
            Schema::rename('purchase_order_items', 'delivery_order_items');

        // delivery_orders mungkin belum ada — buat fresh
        if (!Schema::hasTable('delivery_orders')) {
            Schema::create('delivery_orders', function (Blueprint $table) {
                $table->id();
                $table->string('do_number')->unique();
                $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
                $table->foreignId('vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
                $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('currency')->default('IDR');
                $table->enum('status', ['Done', 'In Progress', 'Cancelled'])->default('In Progress');
                $table->date('order_date');
                $table->string('delivery_type')->nullable();
                $table->string('origin')->nullable();
                $table->string('destination')->nullable();
                $table->string('tracking_number')->nullable();
                $table->date('estimated_arrival')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('delivery_order_items')) {
            Schema::create('delivery_order_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('delivery_order_id')->constrained('delivery_orders')->cascadeOnDelete();
                $table->string('service_name');
                $table->string('unit')->default('kg');
                $table->decimal('qty', 15, 3)->default(0);
                $table->decimal('buy_price', 15, 0)->default(0);
                $table->decimal('sell_price', 15, 0)->default(0);
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        // ──────────────────────────────────────────────
        // 2. RESTRUCTURE vendors TABLE
        // ──────────────────────────────────────────────
        if (Schema::hasColumn('vendors', 'supplier_name'))
            Schema::table('vendors', fn(Blueprint $t) => $t->renameColumn('supplier_name', 'vendor_name'));

        if (Schema::hasColumn('vendors', 'supplier_since'))
            Schema::table('vendors', fn(Blueprint $t) => $t->renameColumn('supplier_since', 'vendor_since'));

        Schema::table('vendors', function (Blueprint $table) {
            if (!Schema::hasColumn('vendors', 'vendor_type'))
                $table->string('vendor_type')->default('External')->after('address');
            if (!Schema::hasColumn('vendors', 'service_type'))
                $table->string('service_type')->nullable()->after('vendor_type');
            if (!Schema::hasColumn('vendors', 'service_mode'))
                $table->string('service_mode')->nullable()->after('service_type');
        });

        DB::statement("UPDATE vendors SET vendor_type = 'External' WHERE vendor_type IS NULL OR vendor_type = ''");

        // Drop kolom chemical-specific (hanya jika masih ada)
        Schema::table('vendors', function (Blueprint $table) {
            $drops = array_filter(['source_type', 'product_category', 'origin_country'],
                fn($c) => Schema::hasColumn('vendors', $c));
            if (!empty($drops)) $table->dropColumn($drops);
        });

        // ──────────────────────────────────────────────
        // 3. RESTRUCTURE vendor_pics TABLE
        // ──────────────────────────────────────────────
        if (Schema::hasColumn('vendor_pics', 'supplier_id'))
            Schema::table('vendor_pics', fn(Blueprint $t) => $t->renameColumn('supplier_id', 'vendor_id'));

        // ──────────────────────────────────────────────
        // 4. RESTRUCTURE vendor_services TABLE
        // ──────────────────────────────────────────────
        if (Schema::hasColumn('vendor_services', 'supplier_id'))
            Schema::table('vendor_services', fn(Blueprint $t) => $t->renameColumn('supplier_id', 'vendor_id'));

        if (Schema::hasColumn('vendor_services', 'product_name'))
            Schema::table('vendor_services', fn(Blueprint $t) => $t->renameColumn('product_name', 'service_name'));

        Schema::table('vendor_services', function (Blueprint $table) {
            if (!Schema::hasColumn('vendor_services', 'tariff'))
                $table->decimal('tariff', 15, 2)->default(0)->after('unit');
            if (!Schema::hasColumn('vendor_services', 'tariff_unit'))
                $table->string('tariff_unit')->default('per kg')->after('tariff');
            if (!Schema::hasColumn('vendor_services', 'route_origin'))
                $table->string('route_origin')->nullable()->after('tariff_unit');
            if (!Schema::hasColumn('vendor_services', 'route_destination'))
                $table->string('route_destination')->nullable()->after('route_origin');
        });

        DB::statement("UPDATE vendor_services SET unit = 'kg' WHERE unit = 'ton'");

        // ──────────────────────────────────────────────
        // 5. RESTRUCTURE delivery_orders TABLE
        // ──────────────────────────────────────────────
        if (Schema::hasColumn('delivery_orders', 'po_number'))
            Schema::table('delivery_orders', fn(Blueprint $t) => $t->renameColumn('po_number', 'do_number'));

        if (Schema::hasColumn('delivery_orders', 'supplier_id'))
            Schema::table('delivery_orders', fn(Blueprint $t) => $t->renameColumn('supplier_id', 'vendor_id'));

        Schema::table('delivery_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('delivery_orders', 'delivery_type'))
                $table->string('delivery_type')->nullable()->after('vendor_id');
            if (!Schema::hasColumn('delivery_orders', 'origin'))
                $table->string('origin')->nullable()->after('delivery_type');
            if (!Schema::hasColumn('delivery_orders', 'destination'))
                $table->string('destination')->nullable()->after('origin');
            if (!Schema::hasColumn('delivery_orders', 'tracking_number'))
                $table->string('tracking_number')->nullable()->after('destination');
            if (!Schema::hasColumn('delivery_orders', 'estimated_arrival'))
                $table->date('estimated_arrival')->nullable()->after('tracking_number');
        });

        DB::statement("UPDATE delivery_orders SET do_number = REPLACE(do_number, 'PO-', 'DO-') WHERE do_number LIKE 'PO-%'");

        // ──────────────────────────────────────────────
        // 6. RESTRUCTURE delivery_order_items TABLE
        // ──────────────────────────────────────────────
        if (Schema::hasColumn('delivery_order_items', 'purchase_order_id'))
            Schema::table('delivery_order_items', fn(Blueprint $t) => $t->renameColumn('purchase_order_id', 'delivery_order_id'));

        if (Schema::hasColumn('delivery_order_items', 'shipment_order_id'))
            Schema::table('delivery_order_items', fn(Blueprint $t) => $t->renameColumn('shipment_order_id', 'delivery_order_id'));

        if (Schema::hasColumn('delivery_order_items', 'product_name'))
            Schema::table('delivery_order_items', fn(Blueprint $t) => $t->renameColumn('product_name', 'service_name'));

        DB::statement("UPDATE delivery_order_items SET unit = 'kg' WHERE unit = 'ton'");
    }

    public function down(): void
    {
        // Reverse: shipment → purchase
        Schema::table('delivery_order_items', function (Blueprint $table) {
            $table->renameColumn('service_name', 'product_name');
            $table->renameColumn('shipment_order_id', 'purchase_order_id');
        });

        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->dropColumn(['delivery_type', 'origin', 'destination', 'tracking_number', 'estimated_arrival']);
        });
        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->renameColumn('vendor_id', 'supplier_id');
            $table->renameColumn('do_number', 'po_number');
        });
        DB::statement("UPDATE delivery_orders SET po_number = REPLACE(po_number, 'DO-', 'PO-')");

        // Reverse: vendor_services → supplier_products
        Schema::table('vendor_services', function (Blueprint $table) {
            $table->dropColumn(['tariff', 'tariff_unit', 'route_origin', 'route_destination']);
        });
        Schema::table('vendor_services', function (Blueprint $table) {
            $table->renameColumn('service_name', 'product_name');
            $table->renameColumn('vendor_id', 'supplier_id');
        });

        // Reverse: vendor_pics → supplier_pics
        Schema::table('vendor_pics', function (Blueprint $table) {
            $table->renameColumn('vendor_id', 'supplier_id');
        });

        // Reverse: vendors → suppliers
        Schema::table('vendors', function (Blueprint $table) {
            $table->string('source_type')->default('Local')->after('address');
            $table->string('product_category')->nullable();
            $table->string('origin_country')->nullable();
            $table->dropColumn(['vendor_type', 'service_type', 'service_mode']);
        });
        Schema::table('vendors', function (Blueprint $table) {
            $table->renameColumn('vendor_name', 'supplier_name');
            $table->renameColumn('vendor_since', 'supplier_since');
        });

        Schema::rename('delivery_order_items', 'purchase_order_items');
        Schema::rename('delivery_orders', 'purchase_orders');
        Schema::rename('vendor_services', 'supplier_products');
        Schema::rename('vendor_pics', 'supplier_pics');
        Schema::rename('vendors', 'suppliers');
    }
};
