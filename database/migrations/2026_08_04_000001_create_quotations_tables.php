<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->string('quotation_number')->unique();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->date('quotation_date');
            $table->string('recipient_name')->default('Yth.');
            $table->string('recipient_title')->default('Bpk/Ibu Pimpinan');
            $table->string('company_name');
            $table->text('recipient_address')->nullable();
            $table->string('attachment')->default('-');
            $table->string('subject')->default('Surat Penawaran Harga');
            $table->text('opening')->nullable();
            $table->json('terms')->nullable();
            $table->text('closing')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('city')->default('Surabaya');
            $table->string('signatory_name');
            $table->string('signatory_title')->default('Direktur');
            $table->string('status')->default('draft');
            $table->timestamps();

            $table->index(['status', 'quotation_date']);
        });

        Schema::create('quotation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained()->cascadeOnDelete();
            $table->string('origin');
            $table->string('destination');
            $table->string('commodity');
            $table->string('tonnage');
            $table->string('unit');
            $table->decimal('rate', 18, 2);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_items');
        Schema::dropIfExists('quotations');
    }
};
