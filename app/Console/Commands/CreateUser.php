<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\UserProvisioner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

/**
 * Cree un compte depuis la console — notamment le premier administrateur,
 * avant qu'il existe quelqu'un pour utiliser la page « Utilisateurs ».
 *
 *     php artisan user:create admin@exemple.fr --admin
 */
class CreateUser extends Command
{
    protected $signature = 'user:create {email} {--admin : Donner le role administrateur} {--password= : Mot de passe (demande sinon)}';

    protected $description = 'Cree un compte (login / mot de passe)';

    public function handle(UserProvisioner $provisioner): int
    {
        $email = (string) $this->argument('email');
        $password = $this->option('password') ?: $this->secret('Mot de passe (12 caractères minimum)');

        $validator = Validator::make(
            ['email' => $email, 'password' => $password],
            ['email' => ['required', 'email', 'max:255'], 'password' => ['required', 'string', 'min:12', 'max:255']],
        );

        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }

        if (User::where('email', $email)->exists()) {
            $this->error('Un compte existe déjà pour '.$email.'.');

            return self::FAILURE;
        }

        $provisioner->create($email, $password, (bool) $this->option('admin'));
        $this->info('Compte créé : '.$email.($this->option('admin') ? ' (administrateur)' : '').'.');

        return self::SUCCESS;
    }
}
