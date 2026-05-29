<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (app()->environment('local')) {
            $email = env('SEED_ADMIN_EMAIL', 'admin@example.com');
            $username = env('SEED_ADMIN_USERNAME', 'admin');
            $password = env('SEED_ADMIN_PASSWORD');

            if (empty($password)) {
                $password = Str::password(16);
                $this->command?->warn("Generated local admin password for {$email}: {$password}");
            }

            User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => 'Admin',
                    'username' => $username,
                    'password' => bcrypt($password),
                    'email_verified_at' => now(),
                    'is_admin' => true,
                ]
            );
        }

        $this->call([
            // RoadMapSeeder::class,
             DemoContentSeeder::class,
          //  QuickSameUserPostsSeeder::class,
        ]);
    }
}
