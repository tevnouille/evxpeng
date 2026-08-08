<?php

namespace App\Console\Commands;

use App\Models\FuelPrice;
use DOMDocument;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use XMLReader;
use ZipArchive;

class BackfillFuelPrices extends Command
{
    protected $signature = 'fuel-prices:backfill {--year=}';

    protected $description = "Reconstruit l'historique quotidien des prix essence (SP95) / diesel (Gazole) depuis l'archive annuelle officielle donnees.roulez-eco.fr, du 1er janvier a hier (aujourd'hui reste gere par le flux instantane)";

    private const ARCHIVE_URL = 'https://donnees.roulez-eco.fr/opendata/annee';

    public function handle(): int
    {
        ini_set('memory_limit', '512M');

        $year = (int) ($this->option('year') ?: now()->year);

        $zipPath = storage_path("app/fuel-prices-{$year}.zip");
        $xmlPath = storage_path("app/fuel-prices-{$year}.xml");

        $this->info("Téléchargement de l'archive annuelle {$year}...");

        $response = Http::timeout(180)->sink($zipPath)->get(self::ARCHIVE_URL);

        if (! $response->successful()) {
            $this->error('Téléchargement échoué : HTTP ' . $response->status());

            return self::FAILURE;
        }

        $zip = new ZipArchive();

        if ($zip->open($zipPath) !== true) {
            $this->error("Impossible d'ouvrir l'archive ZIP.");

            return self::FAILURE;
        }

        $entryName = $zip->getNameIndex(0);
        $zip->extractTo(dirname($xmlPath), $entryName);
        rename(dirname($xmlPath) . '/' . $entryName, $xmlPath);
        $zip->close();
        unlink($zipPath);

        $this->info('Analyse du fichier XML (peut prendre une à deux minutes)...');

        // station_id => ['SP95' => [[timestamp, prix], ...], 'Gazole' => [...]] tries chronologiquement.
        $priceHistories = [];

        $reader = new XMLReader();
        $reader->open($xmlPath);

        while ($reader->read()) {
            if ($reader->nodeType !== XMLReader::ELEMENT || $reader->name !== 'pdv') {
                continue;
            }

            $node = $reader->expand();
            $doc = new DOMDocument();
            $doc->appendChild($doc->importNode($node, true));

            foreach ($doc->getElementsByTagName('prix') as $prixEl) {
                $nom = $prixEl->getAttribute('nom');

                if ($nom !== 'SP95' && $nom !== 'Gazole') {
                    continue;
                }

                $valeur = (float) $prixEl->getAttribute('valeur');

                if ($valeur <= 0) {
                    continue;
                }

                $timestamp = strtotime($prixEl->getAttribute('maj'));
                $stationId = $prixEl->parentNode->getAttribute('id') ?: uniqid('', true);
                $priceHistories[$stationId][$nom][] = [$timestamp, $valeur];
            }

            $reader->next('pdv');
        }

        $reader->close();
        unlink($xmlPath);

        $this->info('Tri des historiques par station (' . count($priceHistories) . ' stations)...');

        foreach ($priceHistories as &$fuels) {
            foreach ($fuels as &$events) {
                usort($events, fn ($a, $b) => $a[0] <=> $b[0]);
            }
        }
        unset($fuels, $events);

        $startDate = Carbon::create($year, 1, 1)->startOfDay();
        $endDate = now()->subDay()->startOfDay();

        if ($startDate->gt($endDate)) {
            $this->info("Rien à faire (l'année {$year} ne commence pas avant hier).");

            return self::SUCCESS;
        }

        $this->info('Calcul des moyennes nationales quotidiennes...');
        $bar = $this->output->createProgressBar($startDate->diffInDays($endDate) + 1);
        $written = 0;

        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            $cutoff = $date->copy()->endOfDay()->timestamp;

            $sp95Sum = 0.0;
            $sp95Count = 0;
            $gazoleSum = 0.0;
            $gazoleCount = 0;

            foreach ($priceHistories as $fuels) {
                if (! empty($fuels['SP95'])) {
                    $price = $this->lastPriceBefore($fuels['SP95'], $cutoff);

                    if ($price !== null) {
                        $sp95Sum += $price;
                        $sp95Count++;
                    }
                }

                if (! empty($fuels['Gazole'])) {
                    $price = $this->lastPriceBefore($fuels['Gazole'], $cutoff);

                    if ($price !== null) {
                        $gazoleSum += $price;
                        $gazoleCount++;
                    }
                }
            }

            if ($sp95Count > 0 && $gazoleCount > 0) {
                FuelPrice::updateOrCreate(
                    ['date' => $date->format('Y-m-d')],
                    [
                        'essence_price' => round($sp95Sum / $sp95Count, 3),
                        'diesel_price' => round($gazoleSum / $gazoleCount, 3),
                    ]
                );
                $written++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Terminé : {$written} jour(s) enregistré(s).");

        return self::SUCCESS;
    }

    /**
     * @param array<int, array{0: int, 1: float}> $events Tries par timestamp croissant.
     */
    private function lastPriceBefore(array $events, int $cutoffTimestamp): ?float
    {
        $lo = 0;
        $hi = count($events) - 1;
        $result = null;

        while ($lo <= $hi) {
            $mid = intdiv($lo + $hi, 2);

            if ($events[$mid][0] <= $cutoffTimestamp) {
                $result = $events[$mid][1];
                $lo = $mid + 1;
            } else {
                $hi = $mid - 1;
            }
        }

        return $result;
    }
}
