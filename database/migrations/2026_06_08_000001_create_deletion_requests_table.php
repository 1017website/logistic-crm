<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel permintaan hapus (deletion request) lintas-entity.
 *
 * Semua role mengajukan permintaan hapus melalui tabel ini.
 * Hanya Administrator yang dapat menyetujui (approve) atau menolak (reject).
 * Saat approve, record target di-soft-delete oleh controller.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('deletion_requests')) {
            return;
        }

        Schema::create('deletion_requests', function (Blueprint $table) {
            $table->id();

            // Target polymorphic: model_type = App\Models\Lead, dst.
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');

            // Label ringkas untuk ditampilkan tanpa harus load model.
            $table->string('model_label')->nullable();   // mis. nama perusahaan / nomor DO
            $table->string('module')->nullable();         // mis. leads, customers, vendors, delivery-orders

            $table->string('status')->default('pending'); // pending | approved | rejected
            $table->text('reason')->nullable();            // alasan dari pengaju
            $table->text('review_note')->nullable();       // catatan admin saat approve/reject

            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();

            $table->index(['model_type', 'model_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deletion_requests');
    }
};
