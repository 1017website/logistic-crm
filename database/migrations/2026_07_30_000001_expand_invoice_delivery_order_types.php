<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('invoices') && !Schema::hasColumn('invoices', 'billing_mode')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->string('billing_mode', 20)->default('combined')->after('jenis')
                    ->comment('combined|separate');
            });
        }

        if (Schema::hasTable('delivery_orders') && !Schema::hasColumn('delivery_orders', 'invoice_status')) {
            Schema::table('delivery_orders', function (Blueprint $table) {
                $table->string('invoice_status', 20)->default('uninvoiced')->after('status')
                    ->comment('uninvoiced|partial|invoiced|paid');
                $table->index('invoice_status', 'do2_invoice_status_idx');
            });

            DB::table('delivery_orders')
                ->where('status', 'invoiced')
                ->update(['invoice_status' => 'invoiced']);
            DB::table('delivery_orders')
                ->where('status', 'paid')
                ->update(['invoice_status' => 'paid']);
        }

        if (Schema::hasTable('invoice_items')) {
            $addDeliveryOrder = !Schema::hasColumn('invoice_items', 'delivery_order_id');
            $addItemType = !Schema::hasColumn('invoice_items', 'item_type');
            $addDescription = !Schema::hasColumn('invoice_items', 'description');

            if ($addDeliveryOrder || $addItemType || $addDescription) {
                Schema::table('invoice_items', function (Blueprint $table) use ($addDeliveryOrder, $addItemType, $addDescription) {
                    if ($addDeliveryOrder) {
                        $table->foreignId('delivery_order_id')->nullable()->after('request_order_id')
                            ->constrained('delivery_orders', 'id', 'invi_delivery_order_id_fk')->nullOnDelete();
                    }
                    if ($addItemType) {
                        $table->string('item_type', 3)->nullable()->after('delivery_order_id')
                            ->comment('TR|NTR');
                    }
                    if ($addDescription) {
                        $table->string('description')->nullable()->after('item_type');
                    }
                });
            }

            // Pertahankan invoice lama: tautkan ke DO final pertama dari Request DO
            // dan gunakan tipe invoice lama sebagai tipe item.
            DB::table('invoice_items')
                ->orderBy('id')
                ->get()
                ->each(function ($item) {
                    $updates = [];

                    if (!$item->delivery_order_id && $item->request_order_id) {
                        $updates['delivery_order_id'] = DB::table('delivery_orders')
                            ->where('request_order_id', $item->request_order_id)
                            ->orderBy('id')
                            ->value('id');
                    }

                    if (!$item->item_type) {
                        $type = DB::table('invoices')->where('id', $item->invoice_id)->value('jenis');
                        $updates['item_type'] = $type === 'NTR' ? 'NTR' : 'TR';
                    }

                    if ($updates !== []) {
                        DB::table('invoice_items')->where('id', $item->id)->update($updates);
                    }
                });

            Schema::table('invoice_items', function (Blueprint $table) {
                $table->index(['delivery_order_id', 'item_type'], 'invi_do_type_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('invoice_items') && Schema::hasColumn('invoice_items', 'delivery_order_id')) {
            Schema::table('invoice_items', function (Blueprint $table) {
                $table->dropIndex('invi_do_type_idx');
                $table->dropForeign('invi_delivery_order_id_fk');
                $table->dropColumn(['delivery_order_id', 'item_type', 'description']);
            });
        }

        if (Schema::hasTable('delivery_orders') && Schema::hasColumn('delivery_orders', 'invoice_status')) {
            Schema::table('delivery_orders', function (Blueprint $table) {
                $table->dropIndex('do2_invoice_status_idx');
                $table->dropColumn('invoice_status');
            });
        }

        if (Schema::hasTable('invoices') && Schema::hasColumn('invoices', 'billing_mode')) {
            Schema::table('invoices', fn(Blueprint $table) => $table->dropColumn('billing_mode'));
        }
    }
};
