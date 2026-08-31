<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')
                ->constrained('invoices', 'id', 'invpay_invoice_id_fk')->cascadeOnDelete();
            $table->date('payment_date');
            $table->decimal('amount', 15, 0);
            $table->string('payment_type', 20)->default('termin')
                ->comment('termin|pelunasan');
            $table->text('note')->nullable();
            $table->foreignId('recorded_by')->nullable()
                ->constrained('users', 'id', 'invpay_recorded_by_fk')->nullOnDelete();
            $table->timestamps();
            $table->index(['invoice_id', 'payment_date'], 'invpay_invoice_date_idx');
        });

        // Invoice lama yang sudah lunas tetap memiliki jejak pembayaran.
        DB::table('invoices')
            ->where('status', 'paid')
            ->orderBy('id')
            ->get()
            ->each(function ($invoice) {
                $amount = (float) ($invoice->grand_total ?: $invoice->total_jual);
                if ($amount <= 0) {
                    return;
                }

                DB::table('invoice_payments')->insert([
                    'invoice_id' => $invoice->id,
                    'payment_date' => $invoice->tgl_pencairan ?: $invoice->tgl_buat ?: now()->toDateString(),
                    'amount' => $amount,
                    'payment_type' => 'pelunasan',
                    'note' => 'Migrasi pembayaran invoice lunas sebelumnya.',
                    'recorded_by' => $invoice->operator_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_payments');
    }
};
