<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable(); // null = semua user (broadcast)
            $table->string('type');           // overdue, new_lead, deal_won, followup, stage_change, target_warning
            $table->string('title');
            $table->text('message');
            $table->string('icon')->default('bell');
            $table->string('icon_color')->default('#3b82f6');
            $table->string('url')->nullable(); // link saat diklik
            $table->boolean('is_read')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'is_read']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
