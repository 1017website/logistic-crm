<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE leads MODIFY COLUMN pipeline_stage ENUM(
            'Identifying','Approaching','Follow Up','Closing','Won','Lost','Maintaining'
        ) NOT NULL DEFAULT 'Identifying'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE leads MODIFY COLUMN pipeline_stage ENUM(
            'Identifying','Approaching','Follow Up','Closing','Won','Lost'
        ) NOT NULL DEFAULT 'Identifying'");
    }
};
