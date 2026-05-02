<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // Seed default values
        $defaults = [
            ['key' => 'company_name',    'value' => 'PT. Logistic Service Indonesia'],
            ['key' => 'company_email',   'value' => 'info@logisticservice.co.id'],
            ['key' => 'company_phone',   'value' => '+62 21 1234 5678'],
            ['key' => 'company_address', 'value' => 'Jl. Raya Logistik No. 88, Jakarta Utara'],
            ['key' => 'company_website', 'value' => ''],
            ['key' => 'currency',        'value' => 'IDR'],
            ['key' => 'timezone',        'value' => 'Asia/Jakarta'],
            ['key' => 'date_format',     'value' => 'DD/MM/YYYY'],
            ['key' => 'language',        'value' => 'id'],
            ['key' => 'notif_overdue',   'value' => '1'],
            ['key' => 'notif_new_lead',  'value' => '1'],
            ['key' => 'notif_deal_won',  'value' => '1'],
            ['key' => 'notif_followup',  'value' => '1'],
            ['key' => 'notif_stage',     'value' => '0'],
            ['key' => 'notif_weekly',    'value' => '0'],
            ['key' => 'notif_target',    'value' => '1'],
        ];

        foreach ($defaults as $d) {
            DB::table('settings')->insert(array_merge($d, ['created_at' => now(), 'updated_at' => now()]));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
