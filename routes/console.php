<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Models\VehicleTelemetry;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ABRP ne rafraichit la donnee qu'environ une fois par heure a l'arret, mais
// bien plus souvent en charge. On echantillonne donc aux cinq minutes : c'est
// ce qui permet d'encadrer une charge rapide de 25 min sans la manquer. Les
// releves redondants sont ecartes par la contrainte d'unicite sur l'horodatage.
Schedule::command('telemetry:poll')
    ->everyFiveMinutes()
    ->withoutOverlapping();

// En charge, la voiture remonte des points bien plus souvent : on suit alors au
// plus pres, sans pour autant interroger l'API toutes les minutes a l'annee.
Schedule::command('telemetry:poll')
    ->everyMinute()
    ->withoutOverlapping()
    ->when(fn () => VehicleTelemetry::where('is_charging', true)
        ->where('recorded_at', '>=', now()->subMinutes(20))
        ->exists());
