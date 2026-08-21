<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('request_orders', function (Blueprint $table) {
            $table->string('operational_status', 20)->default('running')->after('request_status')
                ->comment('running|pending|rescheduled|cancelled');
            $table->text('operational_note')->nullable()->after('operational_status');
            $table->date('rescheduled_for')->nullable()->after('operational_note');
            $table->foreignId('operational_status_changed_by')->nullable()->after('rescheduled_for')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('operational_status_changed_at')->nullable()->after('operational_status_changed_by');
            $table->index('operational_status', 'request_orders_operational_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('request_orders', function (Blueprint $table) {
            $table->dropIndex('request_orders_operational_status_idx');
            $table->dropForeign(['operational_status_changed_by']);
            $table->dropColumn([
                'operational_status',
                'operational_note',
                'rescheduled_for',
                'operational_status_changed_by',
                'operational_status_changed_at',
            ]);
        });
    }
};
