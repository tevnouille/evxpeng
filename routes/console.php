<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
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
