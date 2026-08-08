<?php

namespace Database\Seeders;

use App\Models\PowerRating;
use Illuminate\Database\Seeder;

class PowerRatingSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([7, 11, 22, 50, 150, 300, 400] as $kw) {
            PowerRating::firstOrCreate(['kw' => $kw]);
        }
    }
}
