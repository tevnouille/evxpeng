<?php

namespace App\Console\Commands;

use App\Services\XpengExportParser;
use Illuminate\Console\Command;

/**
 * Importe manuellement un export Xpeng deja telecharge (portail ou API), pour
 * les cas ou l'automatisation quotidienne (`xpeng:sync`) n'a pas encore de
 * quoi tourner -- ou pour rejouer un fichier archive dans storage/app/xpeng.
 */
class ImportXpengExport extends Command
{
    protected $signature = 'xpeng:import {chemin : chemin absolu du zip Xpeng}';

    protected $description = "Agrege a la minute un export Xpeng (zip de CSV) deja present sur le disque.";

    public function handle(XpengExportParser $parser): int
    {
        $chemin = $this->argument('chemin');

        if (! is_file($chemin)) {
            $this->error("Fichier introuvable : $chemin");

            return self::FAILURE;
        }

        $this->info("Import de $chemin...");

        $nombre = $parser->importZip($chemin);

        $this->info("$nombre minute(s) importee(s)/mises a jour dans xpeng_telemetries.");

        return self::SUCCESS;
    }
}
