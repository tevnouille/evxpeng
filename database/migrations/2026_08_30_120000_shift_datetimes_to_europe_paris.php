<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Passage de APP_TIMEZONE de UTC a Europe/Paris.
 *
 * Laravel n'interprete pas les datetime stockes : il les lit dans le fuseau de
 * l'application. Les valeurs ecrites du temps ou l'application etait en UTC
 * seraient donc relues comme des heures de Paris, soit deux heures trop tot en
 * ete. On les convertit une fois pour toutes.
 *
 * La conversion se fait instant par instant (et non par un +2 h fixe) pour que
 * les valeurs anterieures au changement d'heure gardent le bon decalage.
 *
 * Les colonnes de type DATE ne sont pas touchees : decaler une date la ferait
 * changer de jour.
 */
return new class extends Migration
{
    /** @var array<string, array<int, string>> */
    private const COLUMNS = [
        'vehicle_telemetries' => ['recorded_at', 'created_at', 'updated_at'],
        'charging_sessions' => ['telemetry_started_at', 'created_at', 'updated_at'],
        'vehicles' => ['created_at', 'updated_at'],
        'locations' => ['created_at', 'updated_at'],
        'providers' => ['created_at', 'updated_at'],
        'power_ratings' => ['created_at', 'updated_at'],
        'fuel_prices' => ['created_at', 'updated_at'],
    ];

    public function up(): void
    {
        $this->shift('UTC', 'Europe/Paris');
    }

    public function down(): void
    {
        $this->shift('Europe/Paris', 'UTC');
    }

    private function shift(string $from, string $to): void
    {
        $source = new DateTimeZone($from);
        $target = new DateTimeZone($to);

        foreach (self::COLUMNS as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $present = array_values(array_filter($columns, fn ($column) => Schema::hasColumn($table, $column)));

            if ($present === []) {
                continue;
            }

            DB::table($table)->orderBy('id')->chunkById(500, function ($rows) use ($table, $present, $source, $target) {
                foreach ($rows as $row) {
                    $updates = [];

                    foreach ($present as $column) {
                        $value = $row->$column ?? null;

                        if ($value === null) {
                            continue;
                        }

                        $updates[$column] = (new DateTimeImmutable($value, $source))
                            ->setTimezone($target)
                            ->format('Y-m-d H:i:s');
                    }

                    if ($updates !== []) {
                        DB::table($table)->where('id', $row->id)->update($updates);
                    }
                }
            });
        }
    }
};
