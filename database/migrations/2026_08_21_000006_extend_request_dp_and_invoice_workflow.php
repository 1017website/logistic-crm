<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('request_orders', function (Blueprint $table) {
            $table->boolean('dp_request_active')->default(true)->after('dp_status');
            $table->foreignId('cancelled_by')->nullable()->after('dp_reviewed_at')
                ->constrained('users', 'id', 'rdo_cancelled_by_fk')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable()->after('cancelled_by');
            $table->text('cancel_reason')->nullable()->after('cancelled_at');
            $table->index(['request_status', 'order_date'], 'rdo_flow_date_idx');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->string('edit_request_status', 20)->default('none')->after('notes')
                ->comment('none|pending|approved|rejected');
            $table->text('edit_request_reason')->nullable()->after('edit_request_status');
            $table->foreignId('edit_requested_by')->nullable()->after('edit_request_reason')
                ->constrained('users', 'id', 'inv_edit_requested_by_fk')->nullOnDelete();
            $table->timestamp('edit_requested_at')->nullable()->after('edit_requested_by');
            $table->foreignId('edit_reviewed_by')->nullable()->after('edit_requested_at')
                ->constrained('users', 'id', 'inv_edit_reviewed_by_fk')->nullOnDelete();
            $table->timestamp('edit_reviewed_at')->nullable()->after('edit_reviewed_by');
            $table->text('edit_review_note')->nullable()->after('edit_reviewed_at');
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->string('item_name')->nullable()->after('item_type');
            $table->string('truck_type')->nullable()->after('description');
            $table->decimal('quantity', 12, 3)->default(1)->after('truck_type');
            $table->decimal('unit_price', 15, 0)->default(0)->after('quantity');
        });

        DB::table('invoice_items')->orderBy('id')->get()->each(function ($item) {
            $requestOrder = $item->request_order_id
                ? DB::table('request_orders')->where('id', $item->request_order_id)->first()
                : null;

            DB::table('invoice_items')->where('id', $item->id)->update([
                'item_name' => $item->item_type === 'TR' ? 'Trucking' : 'Non-Trucking',
                'truck_type' => $requestOrder?->jenis_truck,
                'quantity' => 1,
                'unit_price' => $item->jual,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn(['item_name', 'truck_type', 'quantity', 'unit_price']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign('inv_edit_requested_by_fk');
            $table->dropForeign('inv_edit_reviewed_by_fk');
            $table->dropColumn([
                'edit_request_status', 'edit_request_reason', 'edit_requested_by',
                'edit_requested_at', 'edit_reviewed_by', 'edit_reviewed_at', 'edit_review_note',
            ]);
        });

        Schema::table('request_orders', function (Blueprint $table) {
            $table->dropIndex('rdo_flow_date_idx');
            $table->dropForeign('rdo_cancelled_by_fk');
            $table->dropColumn(['dp_request_active', 'cancelled_by', 'cancelled_at', 'cancel_reason']);
        });
    }
};
