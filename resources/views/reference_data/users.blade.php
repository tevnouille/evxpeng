@extends('layouts.app')

@section('title', 'Administration — Utilisateurs')

@section('content')
    <a href="{{ route('reference-data.index') }}" class="is-size-7">&larr; Administration</a>
    <h1 class="title mt-2">Utilisateurs</h1>

    <form method="POST" action="{{ route('reference-data.users.store') }}" class="box">
        @csrf
        <h2 class="subtitle">Créer un compte</h2>
        <div class="columns">
            <div class="column">
                <label class="label" for="email">Email (identifiant)</label>
                <input class="input" type="email" id="email" name="email" required maxlength="255"
                       value="{{ old('email') }}" autocomplete="off">
            </div>
            <div class="column">
                <label class="label" for="password">Mot de passe initial</label>
                <input class="input" type="password" id="password" name="password" required minlength="12"
                       maxlength="255" autocomplete="new-password">
                <p class="help">12 caractères minimum. À communiquer à la personne, qui pourra le changer dans « Mon compte ».</p>
            </div>
        </div>
        <div class="field is-grouped is-align-items-center">
            <div class="control">
                <label class="checkbox">
                    <input type="checkbox" name="is_admin" value="1" @checked(old('is_admin'))>
                    Administrateur (gère les comptes et l'état du serveur)
                </label>
            </div>
            <div class="control">
                <button class="button is-link" type="submit">Créer</button>
            </div>
        </div>
        @if ($errors->any())
            <p class="help is-danger">{{ $errors->first() }}</p>
        @endif
    </form>

    <div class="box">
        <div class="table-container">
            <table class="table is-fullwidth is-striped is-hoverable">
                <thead>
                    <tr>
                        <th>Compte</th>
                        <th>État</th>
                        <th class="has-text-right">Recharges</th>
                        <th class="has-text-right">Véhicules</th>
                        <th class="has-text-right">Trajets</th>
                        <th>Dernière visite</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($users as $user)
                        <tr>
                            <td>
                                <strong>{{ $user->email }}</strong>
                                @if ($user->is_admin)
                                    <span class="tag is-dark ml-2">administrateur</span>
                                @endif
                                @if ($user->id === $me->id)
                                    <span class="tag is-light ml-1">vous</span>
                                @endif
                            </td>
                            <td>
                                @if ($user->approved_at)
                                    <span class="tag is-success is-light">actif</span>
                                @else
                                    <span class="tag is-warning is-light">désactivé</span>
                                @endif
                            </td>
                            <td class="has-text-right">{{ $user->charging_sessions_count }}</td>
                            <td class="has-text-right">{{ $user->vehicles_count }}</td>
                            <td class="has-text-right">{{ $user->favorite_routes_count }}</td>
                            <td class="is-size-7">
                                {{ $user->last_seen_at?->diffForHumans() ?? 'jamais venu' }}
                            </td>
                            <td class="has-text-right">
                                @if ($user->id !== $me->id)
                                    <div class="buttons is-right are-small">
                                        <form method="POST" action="{{ route('reference-data.users.role', $user) }}">
                                            @csrf
                                            @method('PUT')
                                            <input type="hidden" name="is_admin" value="{{ $user->is_admin ? 0 : 1 }}">
                                            <button class="button is-light" type="submit">{{ $user->is_admin ? 'Retirer admin' : 'Passer admin' }}</button>
                                        </form>
                                        <form method="POST" action="{{ route('reference-data.users.xpeng', $user) }}">
                                            @csrf
                                            @method('PUT')
                                            <input type="hidden" name="xpeng_access" value="{{ $user->xpeng_access ? 0 : 1 }}">
                                            <button class="button is-light" type="submit">{{ $user->xpeng_access ? 'Retirer Xpeng' : 'Accès Xpeng' }}</button>
                                        </form>
                                        <form method="POST" action="{{ route('reference-data.users.password', $user) }}"
                                              onsubmit="const p = prompt('Nouveau mot de passe pour {{ $user->email }} (12 caractères minimum)'); if (!p) return false; this.password.value = p;">
                                            @csrf
                                            @method('PUT')
                                            <input type="hidden" name="password" value="">
                                            <button class="button is-light" type="submit">Mot de passe</button>
                                        </form>
                                        @if ($user->approved_at)
                                            <form method="POST" action="{{ route('reference-data.users.revoke', $user) }}">
                                                @csrf
                                                @method('PUT')
                                                <button class="button is-warning is-light" type="submit">Désactiver</button>
                                            </form>
                                        @else
                                            <form method="POST" action="{{ route('reference-data.users.approve', $user) }}">
                                                @csrf
                                                @method('PUT')
                                                <button class="button is-success is-light" type="submit">Activer</button>
                                            </form>
                                        @endif

                                        <form method="POST" action="{{ route('reference-data.users.destroy', $user) }}"
                                              onsubmit="return confirm('Supprimer {{ $user->email }} et TOUTES ses données ({{ $user->charging_sessions_count }} recharge(s), {{ $user->vehicles_count }} véhicule(s), {{ $user->favorite_routes_count }} trajet(s)) ? Cette action est définitive.');">
                                            @csrf
                                            @method('DELETE')
                                            <button class="button is-danger is-light" type="submit">Supprimer</button>
                                        </form>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <p class="has-text-grey is-size-7">
            <strong>Désactiver</strong> conserve les données du compte&nbsp;: la personne ne peut plus
            se connecter, mais tout est là si vous le réactivez.
            <strong>Supprimer</strong> efface le compte et tout ce qu'il contient, sans retour possible.
        </p>
    </div>
@endsection
