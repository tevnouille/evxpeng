<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Models\XpengDataExport;
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

// Le prix carburant n'etait rafraichi que par une visite de page (ensureToday)
// ou le bouton admin : un jour sans visite ne comparait le prix a personne.
// Le matin, avant que quiconque ne prenne la route.
Schedule::command('fuel-prices:check')->dailyAt('07:30')->withoutOverlapping();

// Quota Xpeng strict (5 soumissions/24h) : deux passages par jour pour
// commencer, tant que le comportement reel d'une resoumission pendant un
// export "en cours" n'est pas verifie (voir App\Console\Commands\SyncXpengData).
// Peut prendre jusqu'a 80 s (la commande patiente sur place tant que l'export
// n'est pas pret) : sans consequence a cette cadence, ce n'est pas un
// declenchement a la sous-minute comme telemetry:ingest-mqtt plus haut.
Schedule::command('xpeng:sync')->dailyAt('06:15')->withoutOverlapping();

// Repli si le passage de 06h15 n'a pas rapporte de fichier (export encore en
// cours chez Xpeng au-dela des 80 s d'attente, deja vu le 22/09/2026) : un
// second essai plutot que d'attendre le lendemain. Ne se declenche que si
// aucun export du jour n'a reussi, pour rester tres en-deca du quota.
Schedule::command('xpeng:sync')
    ->dailyAt('08:15')
    ->withoutOverlapping()
    ->when(fn () => ! XpengDataExport::whereDate('requested_at', today())->where('statut', 'ok')->exists());
