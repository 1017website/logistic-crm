<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'name'     => 'Administrator',
                'email'    => 'admin@crm.com',
                'password' => Hash::make('password'),
                'role'     => 'Admin',
                'status'   => 'Active',
                'target'   => 0,
                'phone'    => '08100000001',
            ],
            [
                'name'     => 'Budi Santoso',
                'email'    => 'manager@crm.com',
                'password' => Hash::make('password'),
                'role'     => 'Sales Manager',
                'status'   => 'Active',
                'target'   => 2000000000,
                'phone'    => '08100000002',
            ],
            [
                'name'     => 'Rina Anita',
                'email'    => 'sales@crm.com',
                'password' => Hash::make('password'),
                'role'     => 'Sales Executive',
                'status'   => 'Active',
                'target'   => 500000000,
                'phone'    => '08100000003',
            ],
        ];

        foreach ($users as $u) {
            User::updateOrCreate(['email' => $u['email']], $u);
        }
    }
}
