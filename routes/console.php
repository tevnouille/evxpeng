<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Services\VehicleState;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Cadence adaptative : la voiture ne raconte quelque chose que quand elle roule
// ou qu'elle charge. Le reste du temps elle ne remonte qu'un point par heure, et
// interroger l'API plus vite ne ferait que redemander la meme mesure — les
// releves redondants sont d'ailleurs ecartes par la contrainte d'unicite sur
// l'horodatage.
//
// L'etat vient de VehicleState : `is_charging` seul laisserait le roulage a la
// cadence lente, alors que c'est la ou la donnee bouge le plus.
$active = fn () => app(VehicleState::class)->anyActive();

Schedule::command('telemetry:poll')
    ->everyFifteenSeconds()
    ->withoutOverlapping()
    ->when($active);

// A l'arret, une fois par minute. `skip` plutot qu'une condition inverse : sans
// lui, la minute pleine ferait doublon avec le passage a 0 s de la cadence
// rapide.
Schedule::command('telemetry:poll')
    ->everyMinute()
    ->withoutOverlapping()
    ->skip($active);

// La base IRVE bouge de quelques centaines de stations par semaine : un import
// hebdomadaire suffit largement, et il dure plusieurs minutes.
Schedule::command('irve:import')->weeklyOn(1, '04:30')->withoutOverlapping();
