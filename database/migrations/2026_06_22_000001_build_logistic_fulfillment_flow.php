<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Membangun alur fulfillment logistik penuh:
 *
 *   CUSTOMER -> SALES (terima order) -> REQUEST DO (dibuat sales)
 *   -> SALES ADMIN (verifikasi: harga/customer/lokasi/jadwal/kelengkapan)
 *   -> TRANSPORT PLANNER (pilih armada internal / vendor eksternal)
 *   -> APPROVAL PENUGASAN -> [auto generate] DELIVERY ORDER (DO final)
 *   -> SURAT JALAN -> PICKUP -> DELIVERY -> POD
 *   -> SALES ADMIN (verifikasi POD, input biaya, tutup DO)
 *   -> FINANCE (invoice customer, tagihan vendor) -> PAYMENT
 *
 * Perubahan:
 * 1. RENAME delivery_orders -> request_orders (+ items & tambah kolom flow).
 *    Tabel lama "Delivery Orders" sekarang berperan sebagai "Request DO".
 * 2. CREATE delivery_orders (FRESH, struktur baru) = DO final.
 * 3. CREATE order_assignments = penugasan armada/vendor + approval.
 * 4. CREATE order_status_logs = audit trail perpindahan tahap (polymorphic).
 */
return new class extends Migration
{
    public function up(): void
    {
        // ──────────────────────────────────────────────────────────
        // 1. RENAME delivery_orders -> request_orders
        //    (idempotent: skip jika sudah ter-rename)
        // ──────────────────────────────────────────────────────────
        if (Schema::hasTable('delivery_orders') && !Schema::hasTable('request_orders')) {
            Schema::rename('delivery_orders', 'request_orders');
        }
        if (Schema::hasTable('delivery_order_items') && !Schema::hasTable('request_order_items')) {
            Schema::rename('delivery_order_items', 'request_order_items');
        }

        // Rename FK column delivery_order_id -> request_order_id pada items
        if (Schema::hasTable('request_order_items') && Schema::hasColumn('request_order_items', 'delivery_order_id')) {
            Schema::table('request_order_items', function (Blueprint $table) {
                $table->renameColumn('delivery_order_id', 'request_order_id');
            });
        }

        // Tambah kolom flow ke request_orders
        if (Schema::hasTable('request_orders')) {
            Schema::table('request_orders', function (Blueprint $table) {
                if (!Schema::hasColumn('request_orders', 'request_status')) {
                    // Status alur Request DO (sebelum jadi DO final)
                    $table->string('request_status', 30)->default('draft')->after('status')
                        ->comment('draft|verifikasi|dispatch|approval|assigned|rejected|cancelled');
                }
                if (!Schema::hasColumn('request_orders', 'verified_by')) {
                    $table->foreignId('verified_by')->nullable()->after('request_status')
                        ->constrained('users', 'id', 'ro_verified_by_fk')->nullOnDelete();
                }
                if (!Schema::hasColumn('request_orders', 'verified_at')) {
                    $table->timestamp('verified_at')->nullable()->after('verified_by');
                }
                if (!Schema::hasColumn('request_orders', 'verify_note')) {
                    $table->text('verify_note')->nullable()->after('verified_at');
                }
                if (!Schema::hasColumn('request_orders', 'pickup_date')) {
                    $table->date('pickup_date')->nullable()->after('estimated_arrival');
                }
            });

            // Backfill: order lama dianggap sudah "assigned" agar tidak nyangkut di draft.
            DB::table('request_orders')->where('request_status', 'draft')->update(['request_status' => 'assigned']);
        }

        // ──────────────────────────────────────────────────────────
        // 2. CREATE delivery_orders (FRESH) = DO final
        // ──────────────────────────────────────────────────────────
        // Guard: bila ada sisa tabel delivery_orders dari run gagal sebelumnya
        // yang BUKAN struktur baru (tidak punya kolom request_order_id), drop dulu.
        if (Schema::hasTable('delivery_orders') && !Schema::hasColumn('delivery_orders', 'request_order_id')) {
            Schema::dropIfExists('delivery_orders');
        }
        if (!Schema::hasTable('delivery_orders')) {
            Schema::create('delivery_orders', function (Blueprint $table) {
                $table->id();
                $table->string('do_number')->unique();
                $table->foreignId('request_order_id')->nullable()
                    ->constrained('request_orders', 'id', 'do2_request_order_id_fk')->nullOnDelete();
                $table->foreignId('customer_id')->nullable()
                    ->constrained('customers', 'id', 'do2_customer_id_fk')->nullOnDelete();
                $table->foreignId('vendor_id')->nullable()
                    ->constrained('vendors', 'id', 'do2_vendor_id_fk')->nullOnDelete();
                $table->foreignId('user_id')->nullable()
                    ->constrained('users', 'id', 'do2_user_id_fk')->nullOnDelete();

                // Status lapangan DO final
                $table->string('status', 30)->default('surat_jalan')
                    ->comment('surat_jalan|pickup|in_delivery|pod|verifikasi_pod|closed|invoiced|paid|cancelled');

                // Penugasan (snapshot dari order_assignments yang di-approve)
                $table->string('assignment_type', 20)->nullable()->comment('internal|external');
                $table->string('fleet_info')->nullable()->comment('Plat/armada internal atau nama vendor');
                $table->string('driver_name')->nullable();
                $table->string('driver_phone')->nullable();

                // Rute & jadwal
                $table->string('origin')->nullable();
                $table->string('destination')->nullable();
                $table->date('do_date');
                $table->date('pickup_date')->nullable();
                $table->date('delivery_date')->nullable();

                // Dokumen (path file di storage)
                $table->string('surat_jalan_file')->nullable();
                $table->string('pod_file')->nullable();
                $table->timestamp('pod_at')->nullable();
                $table->foreignId('pod_verified_by')->nullable()
                    ->constrained('users', 'id', 'do2_pod_verified_by_fk')->nullOnDelete();
                $table->timestamp('pod_verified_at')->nullable();

                // Biaya & penutupan
                $table->decimal('actual_cost', 15, 0)->default(0)->comment('Biaya aktual vendor/operasional');
                $table->decimal('other_cost', 15, 0)->default(0);
                $table->foreignId('closed_by')->nullable()
                    ->constrained('users', 'id', 'do2_closed_by_fk')->nullOnDelete();
                $table->timestamp('closed_at')->nullable();

                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // ──────────────────────────────────────────────────────────
        // 3. CREATE order_assignments = penugasan + approval
        // ──────────────────────────────────────────────────────────
        if (!Schema::hasTable('order_assignments')) {
            Schema::create('order_assignments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('request_order_id')->constrained('request_orders')->cascadeOnDelete();
                $table->string('assignment_type', 20)->comment('internal|external');
                $table->foreignId('vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
                $table->string('fleet_info')->nullable()->comment('Plat nomor / nama armada internal');
                $table->string('driver_name')->nullable();
                $table->string('driver_phone')->nullable();
                $table->decimal('estimated_cost', 15, 0)->default(0);

                $table->foreignId('planned_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('approval_status', 20)->default('pending')->comment('pending|approved|rejected');
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->text('approval_note')->nullable();

                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        // ──────────────────────────────────────────────────────────
        // 4. CREATE order_status_logs = audit trail (polymorphic)
        //    loggable_type/loggable_id menunjuk ke RequestOrder / DeliveryOrder
        // ──────────────────────────────────────────────────────────
        if (!Schema::hasTable('order_status_logs')) {
            Schema::create('order_status_logs', function (Blueprint $table) {
                $table->id();
                $table->string('loggable_type');
                $table->unsignedBigInteger('loggable_id');
                $table->string('from_status', 30)->nullable();
                $table->string('to_status', 30);
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->text('note')->nullable();
                $table->timestamps();

                $table->index(['loggable_type', 'loggable_id'], 'osl_loggable_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_logs');
        Schema::dropIfExists('order_assignments');
        Schema::dropIfExists('delivery_orders'); // versi baru

        // Kembalikan request_orders -> delivery_orders
        if (Schema::hasTable('request_orders')) {
            Schema::table('request_orders', function (Blueprint $table) {
                foreach (['verified_by', 'pod_verified_by', 'closed_by'] as $col) {
                    // hanya drop yang memang kita tambahkan di request_orders
                }
                foreach (['verify_note', 'verified_at', 'verified_by', 'request_status', 'pickup_date'] as $col) {
                    if (Schema::hasColumn('request_orders', $col)) {
                        try { $table->dropColumn($col); } catch (\Throwable $e) {}
                    }
                }
            });
            if (Schema::hasColumn('request_order_items', 'request_order_id')) {
                Schema::table('request_order_items', function (Blueprint $table) {
                    $table->renameColumn('request_order_id', 'delivery_order_id');
                });
            }
            Schema::rename('request_order_items', 'delivery_order_items');
            Schema::rename('request_orders', 'delivery_orders');
        }
    }
};
