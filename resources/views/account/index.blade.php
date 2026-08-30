@extends('layouts.app')

@section('title', 'Mon compte')

@section('content')
    <h1 class="title">Mon compte</h1>

    <div class="box">
        <div class="columns">
            <div class="column is-6">
                <p class="heading">Identité</p>
                <p class="title is-5">{{ $user->email }}</p>
                <p class="has-text-grey is-size-7">
                    Fournie par la passerelle passkey. Vos recharges, véhicules, localisations,
                    fournisseurs, puissances et trajets favoris vous sont propres&nbsp;: aucun autre
                    compte n'y a accès.
                </p>
            </div>
            <div class="column is-6">
                <p class="heading">Compte créé le</p>
                <p class="title is-5">{{ $user->created_at?->format('d/m/Y') ?? '—' }}</p>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('account.update') }}" class="box">
        @csrf
        @method('PUT')
        <h2 class="subtitle">Alertes SMS de recharge</h2>

        <p class="mb-4">
            Un SMS est envoyé quand la charge franchit
            {{ implode(' %, ', $thresholds) }} %. Il part sur <strong>votre</strong> compte Free Mobile&nbsp;;
            sans identifiants renseignés ici, vous ne recevez aucune alerte.
        </p>

        <div class="columns">
            <div class="column is-4">
                <div class="field">
                    <label class="label" for="free_mobile_user">Identifiant Free Mobile</label>
                    <div class="control">
                        <input class="input" type="text" id="free_mobile_user" name="free_mobile_user"
                               value="{{ old('free_mobile_user', $user->free_mobile_user) }}"
                               placeholder="12345678" autocomplete="off">
                    </div>
                    <p class="help">Votre numéro d'identifiant, pas votre numéro de téléphone.</p>
                </div>
            </div>

            <div class="column is-4">
                <div class="field">
                    <label class="label" for="free_mobile_password">Clé d'identification</label>
                    <div class="control">
                        <input class="input" type="password" id="free_mobile_password" name="free_mobile_password"
                               placeholder="{{ $smsConfigured ? 'Enregistrée — laisser vide pour la conserver' : 'Clé générée par Free' }}"
                               autocomplete="new-password">
                    </div>
                    <p class="help">
                        Espace abonné Free Mobile &rarr; Mes Options &rarr; Notifications par SMS.
                    </p>
                </div>
            </div>

            <div class="column is-4 is-flex is-align-items-flex-end">
                <div class="field">
                    <div class="control">
                        <button class="button is-link" type="submit">Enregistrer</button>
                    </div>
                </div>
            </div>
        </div>
    </form>

    @if ($smsConfigured)
        <div class="box">
            <div class="level is-mobile">
                <div class="level-left">
                    <p>
                        Les identifiants Free ne se vérifient qu'en envoyant réellement un message.
                        <br>
                        <span class="has-text-grey is-size-7">
                            Le résultat est consultable dans le
                            <a href="{{ route('reference-data.sms.index') }}">journal des envois</a>.
                        </span>
                    </p>
                </div>
                <div class="level-right">
                    <form method="POST" action="{{ route('account.test-sms') }}">
                        @csrf
                        <button class="button is-light" type="submit">Envoyer un SMS de test</button>
                    </form>
                </div>
            </div>
        </div>
    @endif
@endsection
