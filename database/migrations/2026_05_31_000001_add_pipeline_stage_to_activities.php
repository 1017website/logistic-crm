<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('activities', 'pipeline_stage')) {
            Schema::table('activities', function (Blueprint $table) {
                $table->string('pipeline_stage', 50)->nullable()->after('status');
            });
        }

        // Backfill dari lead/customer terkait agar activity lama tetap punya konteks pipeline.
        DB::statement("UPDATE activities a JOIN leads l ON l.id = a.lead_id SET a.pipeline_stage = l.pipeline_stage WHERE a.pipeline_stage IS NULL");
        DB::statement("UPDATE activities a JOIN customers c ON c.id = a.customer_id SET a.pipeline_stage = CASE WHEN c.status = 'Existing' THEN 'Maintaining' ELSE 'Identifying' END WHERE a.pipeline_stage IS NULL");
    }

    public function down(): void
    {
        if (Schema::hasColumn('activities', 'pipeline_stage')) {
            Schema::table('activities', function (Blueprint $table) {
                $table->dropColumn('pipeline_stage');
            });
        }
    }
};
