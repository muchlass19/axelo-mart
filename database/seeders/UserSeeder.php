<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['name' => 'Admin Axelo', 'email' => 'admin@axelo.test', 'phone' => '081200000001', 'role' => User::ROLE_ADMIN],
            ['name' => 'Budi Santoso', 'email' => 'budi@axelo.test', 'phone' => '081200000002', 'role' => User::ROLE_CUSTOMER],
            ['name' => 'Siti Aminah', 'email' => 'siti@axelo.test', 'phone' => '081200000003', 'role' => User::ROLE_CUSTOMER],
        ];

        foreach ($users as $user) {
            User::updateOrCreate(['email' => $user['email']], [...$user, 'password' => 'password']);
        }
    }
}
