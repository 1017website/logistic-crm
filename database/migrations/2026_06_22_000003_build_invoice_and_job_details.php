<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tahap 2 alur logistik — melengkapi detail operasional & keuangan:
 *
 * 1. Tambah field operasional muatan ke request_orders (dari form lama:
 *    checker, jenis truk, depo, muat/bongkar + tanggal, komoditi, tujuan,
 *    sektor, no container/seal, grade, no pol, supir, dll).
 * 2. order_job_details = rincian biaya per pekerjaan (anggaran/jual/dibayar/
 *    riil) — sumber HPP & Jual sebuah DO.
 * 3. pekerjaan = master jenis pekerjaan + kode (TR/NTR).
 * 4. invoices + invoice_items = penagihan multi-DO, nomor urut per customer
 *    (jalan terus, tidak reset).
 *
 * Idempotent & nama FK eksplisit untuk hindari bentrok.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Field operasional muatan di request_orders ──
        if (Schema::hasTable('request_orders')) {
            Schema::table('request_orders', function (Blueprint $table) {
                $cols = [
                    'checker'            => fn() => $table->string('checker')->nullable(),
                    'jenis_truck'        => fn() => $table->string('jenis_truck')->nullable(),
                    'depo'               => fn() => $table->string('depo')->nullable(),
                    'muat'               => fn() => $table->string('muat')->nullable(),
                    'tgl_muat'           => fn() => $table->date('tgl_muat')->nullable(),
                    'bongkar'            => fn() => $table->string('bongkar')->nullable(),
                    'tgl_bongkar'        => fn() => $table->date('tgl_bongkar')->nullable(),
                    'komoditi'           => fn() => $table->string('komoditi')->nullable(),
                    'tujuan'             => fn() => $table->string('tujuan')->nullable(),
                    'sektor'             => fn() => $table->string('sektor')->nullable(),
                    'kode_sektor'        => fn() => $table->string('kode_sektor')->nullable(),
                    'no_container'       => fn() => $table->string('no_container')->nullable(),
                    'no_seal'            => fn() => $table->string('no_seal')->nullable(),
                    'grade'              => fn() => $table->string('grade')->nullable(),
                    'no_pol'             => fn() => $table->string('no_pol')->nullable(),
                    'supir'              => fn() => $table->string('supir')->nullable(),
                    'hp_supir'           => fn() => $table->string('hp_supir')->nullable(),
                    'empty_full'         => fn() => $table->string('empty_full')->nullable(),
                    'bongkar_empty_full' => fn() => $table->string('bongkar_empty_full')->nullable(),
                    'kota'               => fn() => $table->string('kota')->nullable(),
                    'kecamatan'          => fn() => $table->string('kecamatan')->nullable(),
                    'kelurahan'          => fn() => $table->string('kelurahan')->nullable(),
                    'keterangan'         => fn() => $table->text('keterangan')->nullable(),
                ];
                foreach ($cols as $name => $make) {
                    if (!Schema::hasColumn('request_orders', $name)) {
                        $make();
                    }
                }
            });
        }

        // ── 2. Master pekerjaan ──
        if (!Schema::hasTable('pekerjaan')) {
            Schema::create('pekerjaan', function (Blueprint $table) {
                $table->id();
                $table->string('name');                       // Trucking, Empty Tambak Langon, dll
                $table->string('code', 20)->nullable();       // TR, NTR
                $table->string('type', 30)->nullable()->comment('TR=Trucking, NTR=Non-Trucking');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // ── 3. order_job_details (rincian biaya per pekerjaan) ──
        if (!Schema::hasTable('order_job_details')) {
            Schema::create('order_job_details', function (Blueprint $table) {
                $table->id();
                $table->foreignId('request_order_id')
                    ->constrained('request_orders', 'id', 'ojd_request_order_id_fk')->cascadeOnDelete();
                $table->foreignId('pekerjaan_id')->nullable()
                    ->constrained('pekerjaan', 'id', 'ojd_pekerjaan_id_fk')->nullOnDelete();
                $table->string('job_name')->nullable()->comment('Snapshot nama pekerjaan');
                $table->string('job_code', 20)->nullable();

                $table->date('tgl_transaksi')->nullable();
                // Anggaran (rencana) vs Riil (aktual)
                $table->decimal('anggaran_biaya', 15, 0)->default(0)->comment('Anggaran HPP');
                $table->decimal('anggaran_jual', 15, 0)->default(0)->comment('Anggaran harga jual');
                $table->decimal('riil_biaya', 15, 0)->default(0)->comment('Realisasi HPP');
                $table->decimal('riil_jual', 15, 0)->default(0)->comment('Realisasi harga jual');
                $table->decimal('dibayar', 15, 0)->default(0);

                $table->foreignId('vendor_id')->nullable()
                    ->constrained('vendors', 'id', 'ojd_vendor_id_fk')->nullOnDelete();
                $table->string('status_pembayaran', 20)->default('Tempo')->comment('Lunas|Tempo');
                $table->date('tgl_realisasi')->nullable();
                $table->text('catatan')->nullable();
                $table->timestamps();
            });
        }

        // ── 4. invoices ──
        if (!Schema::hasTable('invoices')) {
            Schema::create('invoices', function (Blueprint $table) {
                $table->id();
                $table->string('invoice_id')->unique()->comment('IV26060001 dst (internal)');
                $table->string('invoice_number')->nullable()->comment('0001/MIF/FTINV/VI/2026');
                $table->unsignedInteger('customer_seq')->nullable()->comment('Nomor urut per customer (jalan terus)');
                $table->foreignId('customer_id')->nullable()
                    ->constrained('customers', 'id', 'inv_customer_id_fk')->nullOnDelete();

                $table->string('status', 20)->default('draft')->comment('draft|invoice|paid');
                $table->date('tgl_buat')->nullable();
                $table->date('tgl_tempo')->nullable();
                $table->date('tgl_pencairan')->nullable();

                $table->decimal('total_hpp', 15, 0)->default(0);
                $table->decimal('total_jual', 15, 0)->default(0);
                $table->decimal('ppn_persen', 5, 2)->default(0);
                $table->decimal('ppn_nominal', 15, 0)->default(0);
                $table->decimal('grand_total', 15, 0)->default(0);

                $table->string('jenis', 10)->nullable()->comment('TR|NTR|MIX');
                $table->foreignId('operator_id')->nullable()
                    ->constrained('users', 'id', 'inv_operator_id_fk')->nullOnDelete();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // ── 5. invoice_items (DO yang masuk ke invoice) ──
        if (!Schema::hasTable('invoice_items')) {
            Schema::create('invoice_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('invoice_id')
                    ->constrained('invoices', 'id', 'invi_invoice_id_fk')->cascadeOnDelete();
                $table->foreignId('request_order_id')->nullable()
                    ->constrained('request_orders', 'id', 'invi_request_order_id_fk')->nullOnDelete();
                $table->decimal('hpp', 15, 0)->default(0);
                $table->decimal('jual', 15, 0)->default(0);
                $table->timestamps();
            });
        }

        // ── 6. Tautkan request_orders ke invoice (status tertagih) ──
        if (Schema::hasTable('request_orders') && !Schema::hasColumn('request_orders', 'invoice_status')) {
            Schema::table('request_orders', function (Blueprint $table) {
                $table->string('invoice_status', 20)->default('uninvoiced')
                    ->comment('uninvoiced|invoiced|paid');
                $table->boolean('do_approved')->default(false)->comment('Approval DO (jual vs hpp)');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('order_job_details');
        Schema::dropIfExists('pekerjaan');

        if (Schema::hasTable('request_orders')) {
            Schema::table('request_orders', function (Blueprint $table) {
                foreach ([
                    'checker','jenis_truck','depo','muat','tgl_muat','bongkar','tgl_bongkar',
                    'komoditi','tujuan','sektor','kode_sektor','no_container','no_seal','grade',
                    'no_pol','supir','hp_supir','empty_full','bongkar_empty_full','kota',
                    'kecamatan','kelurahan','keterangan','invoice_status','do_approved',
                ] as $col) {
                    if (Schema::hasColumn('request_orders', $col)) {
                        try { $table->dropColumn($col); } catch (\Throwable $e) {}
                    }
                }
            });
        }
    }
};
