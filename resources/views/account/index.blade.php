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
                    Vos recharges, véhicules, localisations,
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

    <form method="POST" action="{{ route('account.preferences') }}" class="box">
        @csrf
        @method('PUT')
        <h2 class="subtitle">Affichage</h2>

        <div class="field">
            <label class="checkbox">
                <input type="checkbox" name="show_fuel_equivalent" value="1"
                       @checked(old('show_fuel_equivalent', $user->show_fuel_equivalent))>
                Afficher l'équivalent carburant dans l'historique
            </label>
            <p class="help">
                Le bloc qui compare vos recharges à ce qu'auraient coûté les mêmes kilomètres
                en essence ou en diesel, sur la page d'un mois. Décoché, l'historique s'en tient
                à l'électrique.
            </p>
        </div>

        <div class="field">
            <div class="control">
                <button class="button is-link" type="submit">Enregistrer</button>
            </div>
        </div>
    </form>

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

    <form method="POST" action="{{ route('account.fuel-alert') }}" class="box">
        @csrf
        @method('PUT')
        <h2 class="subtitle">Alerte prix carburant</h2>

        <p class="mb-4">
            Un SMS part quand le prix moyen national franchit le seuil choisi (à la hausse, une
            fois par franchissement). Laissez vide pour ne recevoir aucune alerte. Part sur le
            même compte Free Mobile que l'alerte de recharge, ci-dessus.
        </p>

        <div class="columns">
            <div class="column is-4">
                <div class="field">
                    <label class="label" for="fuel_alert_essence_price">Seuil essence (€/L)</label>
                    <div class="control">
                        <input class="input" type="number" id="fuel_alert_essence_price" name="fuel_alert_essence_price"
                               value="{{ old('fuel_alert_essence_price', $user->fuel_alert_essence_price) }}"
                               min="0" max="5" step="0.001" placeholder="ex. 1,900">
                    </div>
                </div>
            </div>

            <div class="column is-4">
                <div class="field">
                    <label class="label" for="fuel_alert_diesel_price">Seuil diesel (€/L)</label>
                    <div class="control">
                        <input class="input" type="number" id="fuel_alert_diesel_price" name="fuel_alert_diesel_price"
                               value="{{ old('fuel_alert_diesel_price', $user->fuel_alert_diesel_price) }}"
                               min="0" max="5" step="0.001" placeholder="ex. 1,900">
                    </div>
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
            <div class="columns is-vcentered">
                <div class="column">
                    <p>
                        Les identifiants Free ne se vérifient qu'en envoyant réellement un message.
                        <br>
                        <span class="has-text-grey is-size-7">
                            Le résultat est consultable dans le
                            <a href="{{ route('reference-data.sms.index') }}">journal des envois</a>.
                            Free répond parfois après plusieurs dizaines de secondes&nbsp;: un envoi
                            noté « délai dépassé » est peut-être arrivé quand même.
                        </span>
                    </p>
                </div>
                <div class="column is-narrow">
                    <form method="POST" action="{{ route('account.test-sms') }}">
                        @csrf
                        <button class="button is-light" type="submit">Envoyer un SMS de test</button>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <form method="POST" action="{{ route('account.password') }}" class="box">
        @csrf
        @method('PUT')
        <h2 class="subtitle">Mot de passe</h2>

        <div class="field">
            <label class="label" for="current_password">Mot de passe actuel</label>
            <input class="input @error('current_password') is-danger @enderror" type="password"
                   id="current_password" name="current_password" required autocomplete="current-password">
            @error('current_password')
                <p class="help is-danger">{{ $message }}</p>
            @enderror
        </div>
        <div class="field">
            <label class="label" for="password">Nouveau mot de passe</label>
            <input class="input @error('password') is-danger @enderror" type="password"
                   id="password" name="password" required minlength="12" autocomplete="new-password">
            @error('password')
                <p class="help is-danger">{{ $message }}</p>
            @enderror
            <p class="help">12 caractères minimum.</p>
        </div>
        <div class="field">
            <label class="label" for="password_confirmation">Confirmer</label>
            <input class="input" type="password" id="password_confirmation" name="password_confirmation"
                   required minlength="12" autocomplete="new-password">
        </div>
        <button class="button is-link" type="submit">Changer le mot de passe</button>
        <p class="help mt-2">Les sessions ouvertes sur vos autres appareils sont fermées.</p>
    </form>

    <div class="box">
        <div class="columns is-vcentered">
            <div class="column">
                <p>Se déconnecter ferme la session de ce navigateur.</p>
            </div>
            <div class="column is-narrow">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="button is-light" type="submit">Se déconnecter</button>
                </form>
            </div>
        </div>
    </div>
@endsection
