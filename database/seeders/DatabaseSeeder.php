<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(PowerRatingSeeder::class);
        $this->call(VehicleSeeder::class);
    }
}
