<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('loading_orders', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->date('letter_date');
            foreach (['city', 'recipient', 'subject', 'route', 'po_number', 'mod_number', 'driver_name', 'vehicle_number', 'driver_phone', 'vehicle_type', 'signatory_name', 'signatory_title'] as $field) {
                $table->string($field)->nullable();
            }
            foreach (['opening', 'terms', 'closing', 'recipient_address'] as $field) {
                $table->text($field)->nullable();
            }
            $table->json('company');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loading_orders');
    }
};
