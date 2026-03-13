<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{

    public function run(): void
    {
        // Admin-User für Filament
        User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@musikschule.de',
            'password' => bcrypt('password'),
        ]);

        User::factory()->create([
            'name' => 'Steffen Fasselt',
            'email' => 'sfasselt@pauly-it.com',
            'password' => bcrypt('Demopass1111'),
        ]);

        $this->call([
            StudentSeeder::class,
            EmailTemplateSeeder::class,
            CampaignSeeder::class,
            PageSeeder::class,
        ]);
    }
}
