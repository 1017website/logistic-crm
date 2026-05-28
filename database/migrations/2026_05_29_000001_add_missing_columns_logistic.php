<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Cover semua migration lama yang dihapus:
 * - soft_deletes untuk leads, customers, vendors, activities, delivery_orders
 * - user_id di delivery_orders (dulu purchase_orders)
 * - tabel customer_products
 * - tabel customer_products rename field (product_name → service_name di context logistic diabaikan,
 *   customer_products tetap pakai product_name karena ini dari sisi customer/demand)
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Soft Deletes ──────────────────────────────────
        $softDeleteTables = ['leads', 'customers', 'vendors', 'activities', 'delivery_orders'];

        foreach ($softDeleteTables as $table) {
            if (Schema::hasTable($table) && !Schema::hasColumn($table, 'deleted_at')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->softDeletes();
                });
            }
        }

        // ── 2. user_id di delivery_orders ───────────────────
        if (Schema::hasTable('delivery_orders') && !Schema::hasColumn('delivery_orders', 'user_id')) {
            Schema::table('delivery_orders', function (Blueprint $table) {
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete()->after('lead_id');
            });

            // Backfill dari lead
            DB::statement('UPDATE delivery_orders do
                JOIN leads l ON l.id = do.lead_id
                SET do.user_id = l.user_id
                WHERE do.user_id IS NULL AND do.lead_id IS NOT NULL');
        }

        // ── 3. customer_products ─────────────────────────────
        if (!Schema::hasTable('customer_products')) {
            Schema::create('customer_products', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
                $table->string('product_name');   // tetap product_name (dari sisi kebutuhan customer)
                $table->string('unit')->default('kg');
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        // ── 4. lead_products ─────────────────────────────────
        if (!Schema::hasTable('lead_products')) {
            Schema::create('lead_products', function (Blueprint $table) {
                $table->id();
                $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
                $table->string('product_name');
                $table->decimal('qty', 15, 3)->default(0);
                $table->string('unit')->default('kg');
                $table->timestamps();
            });
        }

        // ── 5. lead_pics & customer_pics ─────────────────────
        if (!Schema::hasTable('lead_pics')) {
            Schema::create('lead_pics', function (Blueprint $table) {
                $table->id();
                $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
                $table->string('pic_name');
                $table->string('pic_position')->nullable();
                $table->string('phone')->nullable();
                $table->string('email')->nullable();
                $table->boolean('is_primary')->default(false);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('customer_pics')) {
            Schema::create('customer_pics', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
                $table->string('pic_name');
                $table->string('pic_position')->nullable();
                $table->string('phone')->nullable();
                $table->string('email')->nullable();
                $table->boolean('is_primary')->default(false);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_pics');
        Schema::dropIfExists('lead_pics');
        Schema::dropIfExists('lead_products');
        Schema::dropIfExists('customer_products');

        $tables = ['leads', 'customers', 'vendors', 'activities', 'delivery_orders'];
        foreach ($tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'deleted_at')) {
                Schema::table($table, fn(Blueprint $t) => $t->dropSoftDeletes());
            }
        }
    }
};
