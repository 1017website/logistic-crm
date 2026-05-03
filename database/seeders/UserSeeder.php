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
            // Admin
            [
                'name'     => 'Administrator',
                'email'    => 'admin@crm.com',
                'password' => Hash::make('password'),
                'role'     => 'Admin',
                'status'   => 'Active',
                'target'   => 0,
                'phone'    => '08100000001',
                'position' => 'System Administrator',
            ],
            // Sales Manager
            [
                'name'     => 'Budi Santoso',
                'email'    => 'manager@crm.com',
                'password' => Hash::make('password'),
                'role'     => 'Sales Manager',
                'status'   => 'Active',
                'target'   => 2000000000,
                'phone'    => '08100000002',
                'position' => 'Sales Manager',
            ],
            // Sales Executives
            [
                'name'     => 'Rina Anita',
                'email'    => 'sales@crm.com',
                'password' => Hash::make('password'),
                'role'     => 'Sales Executive',
                'status'   => 'Active',
                'target'   => 500000000,
                'phone'    => '08100000003',
                'position' => 'Sales Executive',
            ],
            [
                'name'     => 'Dedi Suhendra',
                'email'    => 'dedi@crm.com',
                'password' => Hash::make('password'),
                'role'     => 'Sales Executive',
                'status'   => 'Active',
                'target'   => 500000000,
                'phone'    => '08100000004',
                'position' => 'Sales Executive',
            ],
            [
                'name'     => 'Sales A',
                'email'    => 'salesa@crm.com',
                'password' => Hash::make('password'),
                'role'     => 'Sales Executive',
                'status'   => 'Active',
                'target'   => 300000000,
                'phone'    => '08100000005',
                'position' => 'Junior Sales Executive',
            ],
        ];

        foreach ($users as $u) {
            User::updateOrCreate(['email' => $u['email']], $u);
        }
    }
}
