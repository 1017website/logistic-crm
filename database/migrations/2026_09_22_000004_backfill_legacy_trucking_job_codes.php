<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('order_job_details')
            || !Schema::hasColumn('order_job_details', 'job_code')) {
            return;
        }

        DB::table('order_job_details')
            ->whereNull('job_code')
            ->whereRaw('LOWER(job_name) LIKE ?', ['%truck%'])
            ->update(['job_code' => 'TR']);
    }

    public function down(): void
    {
        // Tidak mengosongkan kode karena data dapat sudah dikoreksi user.
    }
};
