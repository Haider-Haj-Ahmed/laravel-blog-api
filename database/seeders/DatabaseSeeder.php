<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // User::factory(10)->create([]);
        // Post::factory(20)->create();
        // Comment::factory(50)->create();
        if (app()->environment('local')) {
            $email = env('SEED_ADMIN_EMAIL', 'admin@example.com');
            $username = env('SEED_ADMIN_USERNAME', 'admin');
            $password = env('SEED_ADMIN_PASSWORD');

            if (! is_string($password) || $password === '') {
                $password = Str::password(16);
                $this->command?->warn("Generated local admin password for {$email}: {$password}");
            }

            User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => 'admin',
                    'username' => $username,
                    'password' => bcrypt($password),
                    'is_admin' => true,
                ]
            );
        }

        $this->call(RoadMapSeeder::class);
    }
}
