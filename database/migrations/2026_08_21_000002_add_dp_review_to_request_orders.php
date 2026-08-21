<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('request_orders', function (Blueprint $table) {
            $table->string('dp_status', 20)->default('pending')->after('operational_status_changed_at')
                ->comment('pending|taken|not_taken');
            $table->decimal('dp_amount', 15, 0)->default(0)->after('dp_status');
            $table->text('dp_note')->nullable()->after('dp_amount');
            $table->foreignId('dp_reviewed_by')->nullable()->after('dp_note')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('dp_reviewed_at')->nullable()->after('dp_reviewed_by');
            $table->index('dp_status', 'request_orders_dp_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('request_orders', function (Blueprint $table) {
            $table->dropIndex('request_orders_dp_status_idx');
            $table->dropForeign(['dp_reviewed_by']);
            $table->dropColumn(['dp_status', 'dp_amount', 'dp_note', 'dp_reviewed_by', 'dp_reviewed_at']);
        });
    }
};
