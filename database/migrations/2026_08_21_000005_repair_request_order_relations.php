<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const RELATIONS = [
        'delivery_orders' => ['constraint' => 'do2_request_order_id_fk', 'delete' => 'set null'],
        'invoice_items' => ['constraint' => 'invi_request_order_id_fk', 'delete' => 'set null'],
        'order_assignments' => ['constraint' => 'order_assignments_request_order_id_foreign', 'delete' => 'cascade'],
        'order_job_details' => ['constraint' => 'ojd_request_order_id_fk', 'delete' => 'cascade'],
        'request_order_items' => ['constraint' => 'roi_request_order_id_fk', 'delete' => 'cascade'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('request_orders')) {
            return;
        }

        $this->createQuarantineTable();

        DB::transaction(function () {
            foreach (array_keys(self::RELATIONS) as $table) {
                $this->quarantineInvalidRows($table);
            }
        });

        foreach (self::RELATIONS as $table => $definition) {
            $this->addRequestOrderForeignKey($table, $definition);
        }
    }

    public function down(): void
    {
        foreach (array_reverse(self::RELATIONS, true) as $table => $definition) {
            if ($this->hasRequestOrderForeignKey($table)) {
                Schema::table($table, function (Blueprint $blueprint) use ($definition) {
                    $blueprint->dropForeign($definition['constraint']);
                });
            }
        }

        if (! Schema::hasTable('request_order_relation_quarantine')) {
            return;
        }

        foreach (array_keys(self::RELATIONS) as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            DB::table('request_order_relation_quarantine')
                ->where('source_table', $table)
                ->orderBy('source_id')
                ->each(function ($row) use ($table) {
                    $payload = json_decode($row->payload, true, flags: JSON_THROW_ON_ERROR);
                    DB::table($table)->insertOrIgnore($payload);
                });
        }

        Schema::dropIfExists('request_order_relation_quarantine');
    }

    private function createQuarantineTable(): void
    {
        if (Schema::hasTable('request_order_relation_quarantine')) {
            return;
        }

        Schema::create('request_order_relation_quarantine', function (Blueprint $table) {
            $table->id();
            $table->string('source_table', 64);
            $table->unsignedBigInteger('source_id');
            $table->string('reason', 40);
            $table->longText('payload');
            $table->timestamp('quarantined_at')->useCurrent();
            $table->unique(['source_table', 'source_id'], 'rorq_source_unique');
        });
    }

    private function quarantineInvalidRows(string $table): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'request_order_id')) {
            return;
        }

        $orphanIds = DB::table($table . ' as child')
            ->leftJoin('request_orders as parent', 'parent.id', '=', 'child.request_order_id')
            ->whereNotNull('child.request_order_id')
            ->whereNull('parent.id')
            ->pluck('child.id');

        $staleIds = collect();
        if (Schema::hasColumn($table, 'created_at')) {
            $staleIds = DB::table($table . ' as child')
                ->join('request_orders as parent', 'parent.id', '=', 'child.request_order_id')
                ->whereNotNull('child.created_at')
                ->whereNotNull('parent.created_at')
                ->whereColumn('child.created_at', '<', 'parent.created_at')
                ->pluck('child.id');
        }

        $invalidIds = $orphanIds->merge($staleIds)->unique()->values();
        if ($invalidIds->isEmpty()) {
            return;
        }

        $orphanLookup = $orphanIds->flip();
        DB::table($table)
            ->whereIn('id', $invalidIds)
            ->orderBy('id')
            ->get()
            ->each(function ($row) use ($table, $orphanLookup) {
                DB::table('request_order_relation_quarantine')->insertOrIgnore([
                    'source_table' => $table,
                    'source_id' => $row->id,
                    'reason' => $orphanLookup->has($row->id) ? 'orphan_parent_missing' : 'older_than_parent',
                    'payload' => json_encode((array) $row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                    'quarantined_at' => now(),
                ]);
            });

        DB::table($table)->whereIn('id', $invalidIds)->delete();
    }

    private function addRequestOrderForeignKey(string $table, array $definition): void
    {
        if (! Schema::hasTable($table)
            || ! Schema::hasColumn($table, 'request_order_id')
            || $this->hasRequestOrderForeignKey($table)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($definition) {
            $foreign = $blueprint->foreign('request_order_id', $definition['constraint'])
                ->references('id')
                ->on('request_orders');

            $definition['delete'] === 'cascade'
                ? $foreign->cascadeOnDelete()
                : $foreign->nullOnDelete();
        });
    }

    private function hasRequestOrderForeignKey(string $table): bool
    {
        if (! Schema::hasTable($table)) {
            return false;
        }

        return collect(Schema::getForeignKeys($table))
            ->contains(fn (array $foreign) => in_array('request_order_id', $foreign['columns'], true)
                && $foreign['foreign_table'] === 'request_orders');
    }
};
