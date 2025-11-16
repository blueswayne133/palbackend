<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\Admin;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        Admin::create([
            'name' => 'Super Admin',
            'email' => 'admin@mail.com',
            'password' => Hash::make('12345678'),
            'role' => 'super_admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        Admin::create([
            'name' => 'Admin User',
            'email' => 'admin@paypal.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        Admin::create([
            'name' => 'Support Staff',
            'email' => 'support@paypal.com',
            'password' => Hash::make('password123'),
            'role' => 'support',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
    }
}