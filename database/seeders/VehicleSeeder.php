<?php

namespace Database\Seeders;

use App\Models\Vehicle;
use Illuminate\Database\Seeder;

class VehicleSeeder extends Seeder
{
    public function run(): void
    {
        Vehicle::firstOrCreate(['name' => 'Ma voiture'], ['is_default' => true]);
    }
}
