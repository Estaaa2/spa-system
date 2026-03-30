<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::updateOrCreate(
            ['email' => 'levictas.dev@gmail.com'],
            [
                'first_name' => 'System',
                'middle_name' => 'Admin',
                'last_name' => 'User',
                'password' => Hash::make('admin123'),
                'is_owner' => false,
            ]
        );

        $admin->syncRoles(['admin']);
    }
}
