<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Services\VehicleState;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Le boitier OBD pousse en continu vers le broker de la maison. Rien ne bride
// plus la cadence : ni quota d'API, ni traitement par lots chez un tiers, et le
// tampon est un fichier local. On vide donc aux quinze secondes, de quoi tenir
// le rafraichissement de trente secondes de la page « Ma voiture ».
//
// La commande decide elle-meme du pas d'ecriture en base — quinze secondes en
// charge ou en roulage, une minute a l'arret : la vider plus souvent ne remplit
// pas la table pour autant.
// En charge, on vide le tampon toutes les cinq secondes : c'est la cadence a
// laquelle la page « Ma voiture » se recharge, et rafraichir l'ecran plus vite
// que la collecte ne montrerait rien de neuf.
$charging = fn () => app(VehicleState::class)->anyCharging();

Schedule::command('telemetry:ingest-mqtt')
    ->everyFiveSeconds()
    ->withoutOverlapping()
    ->when($charging);

// Le reste du temps, le quart de minute suffit — la page elle-meme ne se
// recharge alors qu'aux vingt secondes en roulage, a la minute a l'arret.
Schedule::command('telemetry:ingest-mqtt')
    ->everyFifteenSeconds()
    ->withoutOverlapping()
    ->skip($charging);

// La base IRVE bouge de quelques centaines de stations par semaine : un import
// hebdomadaire suffit largement, et il dure plusieurs minutes.
Schedule::command('irve:import')->weeklyOn(1, '04:30')->withoutOverlapping();
