<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ABRP ne rafraichit la donnee qu'environ une fois par heure a l'arret : un
// passage au quart d'heure suffit largement a ne rien manquer en charge.
Schedule::command('telemetry:poll')
    ->everyFifteenMinutes()
    ->withoutOverlapping();
