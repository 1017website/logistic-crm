<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('leads')) {
            DB::table('leads')->where('pipeline_stage', 'Closing')->update(['pipeline_stage' => 'Won']);
        }
    }

    public function down(): void
    {
        // Tidak dikembalikan ke Closing karena setelah revisi business flow Closing disatukan ke Won.
    }
};
