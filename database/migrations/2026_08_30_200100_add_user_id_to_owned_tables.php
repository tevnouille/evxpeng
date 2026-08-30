<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Cloisonne les donnees par utilisateur.
 *
 * L'application etait mono-compte ; l'ouverture a plusieurs passkeys impose que
 * chacun ne voie que ses recharges, ses vehicules et ses listes de reference.
 *
 * Les tables restees communes le sont a dessein : `charging_stations` (base
 * nationale IRVE) et `fuel_prices` sont des donnees publiques importees, pas des
 * donnees utilisateur. `vehicle_telemetries` et `charge_alerts` sont cloisonnees
 * par ricochet, via le vehicule auquel elles appartiennent.
 */
return new class extends Migration
{
    /** table => index unique a transformer en unique par utilisateur. */
    private const TABLES = [
        'vehicles' => 'name',
        'locations' => 'name',
        'providers' => 'name',
        'power_ratings' => 'kw',
        'charging_sessions' => null,
        'favorite_routes' => null,
        'sms_messages' => null,
    ];

    public function up(): void
    {
        $ownerId = $this->owner();

        foreach (array_keys(self::TABLES) as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->foreignId('user_id')->nullable()->after('id');
            });

            DB::table($table)->update(['user_id' => $ownerId]);

            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->foreignId('user_id')->nullable(false)->change();
                // Supprimer un compte emporte ses donnees : les garder
                // orphelines n'aurait aucun sens ici.
                $blueprint->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
                $blueprint->index('user_id', $table.'_user_id_index');
            });
        }

        // Les libelles ne sont plus uniques dans l'absolu mais par compte : deux
        // utilisateurs ont le droit d'avoir chacun leur "Maison".
        foreach (self::TABLES as $table => $column) {
            if ($column === null) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table, $column) {
                $blueprint->dropUnique($table.'_'.$column.'_unique');
                $blueprint->unique(['user_id', $column], $table.'_user_id_'.$column.'_unique');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table => $column) {
            Schema::table($table, function (Blueprint $blueprint) use ($table, $column) {
                if ($column !== null) {
                    $blueprint->dropUnique($table.'_user_id_'.$column.'_unique');
                    $blueprint->unique($column, $table.'_'.$column.'_unique');
                }

                $blueprint->dropForeign(['user_id']);
                $blueprint->dropColumn('user_id');
            });
        }
    }

    /**
     * Compte auquel rattacher l'existant. La base est mono-compte a ce stade :
     * tout revient au proprietaire historique.
     */
    private function owner(): int
    {
        $email = env('EV_OWNER_EMAIL', 'atran@lolinux.org');
        $existing = DB::table('users')->where('email', $email)->value('id');

        if ($existing !== null) {
            return (int) $existing;
        }

        return (int) DB::table('users')->insertGetId([
            'name' => Str::before($email, '@'),
            'email' => $email,
            'password' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
