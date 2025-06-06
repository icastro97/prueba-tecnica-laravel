<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TestUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
          User::updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Usuario de Prueba',
                'email' => 'test@example.com',
                'password' => Hash::make('password'),
            ]
        );
    }
}
