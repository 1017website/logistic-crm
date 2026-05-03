<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tambah kolom sales ke tabel users
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'position')) {
                $table->string('position')->nullable()->after('name');
            }
            if (!Schema::hasColumn('users', 'avatar')) {
                $table->string('avatar')->nullable()->after('position');
            }
            if (!Schema::hasColumn('users', 'target')) {
                $table->bigInteger('target')->default(500000000)->after('avatar');
            }
            if (!Schema::hasColumn('users', 'role')) {
                $table->string('role')->default('Sales Executive')->after('target');
            }
            if (!Schema::hasColumn('users', 'status')) {
                $table->enum('status', ['Active', 'Non-Active'])->default('Active')->after('role');
            }
            if (!Schema::hasColumn('users', 'phone')) {
                $table->string('phone')->nullable()->after('email');
            }
        });

        // 2. Migrasi data dari sales_users ke users
        if (Schema::hasTable('sales_users')) {
            $salesUsers = DB::table('sales_users')->get();
            foreach ($salesUsers as $su) {
                // Cek apakah email sudah ada di users
                $existing = DB::table('users')->where('email', $su->email)->first();
                if (!$existing) {
                    DB::table('users')->insert([
                        'name'       => $su->name,
                        'email'      => $su->email,
                        'password'   => Hash::make('password'),
                        'phone'      => $su->phone,
                        'position'   => $su->position ?? 'Sales Executive',
                        'role'       => 'Sales Executive',
                        'status'     => 'Active',
                        'target'     => 500000000,
                        'created_at' => $su->created_at,
                        'updated_at' => $su->updated_at,
                    ]);
                }
            }

            // 3. Update FK di semua tabel: ganti sales_user_id → user_id
            $tables = ['leads', 'customers', 'activities'];
            foreach ($tables as $table) {
                if (!Schema::hasTable($table)) continue;

                // Tambah kolom user_id dulu
                if (!Schema::hasColumn($table, 'user_id')) {
                    Schema::table($table, function (Blueprint $t) {
                        $t->unsignedBigInteger('user_id')->nullable()->after('sales_user_id');
                    });
                }

                // Isi user_id berdasarkan sales_user_id lama
                $salesUsers = DB::table('sales_users')->get();
                foreach ($salesUsers as $su) {
                    $newUser = DB::table('users')->where('email', $su->email)->first();
                    if ($newUser) {
                        DB::table($table)
                            ->where('sales_user_id', $su->id)
                            ->update(['user_id' => $newUser->id]);
                    }
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(array_filter(['position', 'avatar', 'target', 'role', 'status', 'phone'], fn($c) => Schema::hasColumn('users', $c)));
        });
    }
};
