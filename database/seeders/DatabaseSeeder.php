<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

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
        User::create([
            'name' => 'admin',
            'email' => 'admin@exampl.com',
            'password' => bcrypt('password'),
            'username' => 'admin',
            'is_admin' => true,
        ]);
        $this->call(RoadMapSeeder::class);
    }
}
