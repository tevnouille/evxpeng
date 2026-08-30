@extends('layouts.app')

@section('title', 'Administration — Utilisateurs')

@section('content')
    <a href="{{ route('reference-data.index') }}" class="is-size-7">&larr; Administration</a>
    <h1 class="title mt-2">Utilisateurs</h1>

    <div class="notification is-info is-light">
        L'identification reste le <strong>passkey</strong>&nbsp;: cette page ne crée pas d'identité, elle
        décide seulement qui, parmi les porteurs de passkey du domaine, a le droit d'entrer ici.
        Un passkey donne accès aux autres services (tevflix, frigate…)&nbsp;; le retirer là-bas
        couperait tout, alors qu'un accès retiré ici ne concerne que cette application.
        <br>
        Les passkeys eux-mêmes se gèrent sur
        <a href="https://pk.lolinux.org/admin/" target="_blank" rel="noopener">pk.lolinux.org</a>.
    </div>

    <form method="POST" action="{{ route('reference-data.users.store') }}" class="box">
        @csrf
        <div class="field is-grouped is-align-items-flex-end">
            <div class="control is-expanded">
                <label class="label" for="email">Autoriser un email</label>
                <input class="input" type="email" id="email" name="email" required maxlength="255"
                       value="{{ old('email') }}" placeholder="prenom.nom@exemple.fr">
                <p class="help">
                    La personne se connecte ensuite avec son passkey et trouve l'application prête.
                    Sans cette autorisation préalable, sa première visite crée un compte
                    <em>en attente</em> et elle voit un refus.
                </p>
            </div>
            <div class="control">
                <button class="button is-link" type="submit">Autoriser</button>
            </div>
        </div>
    </form>

    <div class="box">
        <div class="table-container">
            <table class="table is-fullwidth is-striped is-hoverable">
                <thead>
                    <tr>
                        <th>Compte</th>
                        <th>Accès</th>
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
                                    <span class="tag is-success is-light">autorisé</span>
                                @else
                                    <span class="tag is-warning is-light">en attente</span>
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
                                        @if ($user->approved_at)
                                            <form method="POST" action="{{ route('reference-data.users.revoke', $user) }}">
                                                @csrf
                                                @method('PUT')
                                                <button class="button is-warning is-light" type="submit">Retirer l'accès</button>
                                            </form>
                                        @else
                                            <form method="POST" action="{{ route('reference-data.users.approve', $user) }}">
                                                @csrf
                                                @method('PUT')
                                                <button class="button is-success is-light" type="submit">Autoriser</button>
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
            <strong>Retirer l'accès</strong> conserve les données du compte&nbsp;: la personne ne peut plus
            entrer, mais tout est là si vous la réautorisez.
            <strong>Supprimer</strong> efface le compte et tout ce qu'il contient, sans retour possible.
        </p>
    </div>
@endsection
