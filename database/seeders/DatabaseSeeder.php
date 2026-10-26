<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Admin login: admin@gmail.com / password
        User::updateOrCreate(
            ['email' => 'admin@gmail.com'],
            ['name' => 'Admin FitQuarters', 'password' => Hash::make('password'), 'email_verified_at' => now()]
        );
        // keep legacy admin@fitquarters.id if not exists
        User::firstOrCreate(
            ['email' => 'admin@fitquarters.id'],
            ['name' => 'Admin FitQuarters', 'password' => Hash::make('password'), 'email_verified_at' => now()]
        );
    }
}
