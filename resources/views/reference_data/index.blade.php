@extends('layouts.app')

@section('title', 'Administration')

@section('content')
    <h1 class="title">Administration</h1>
    <p class="subtitle is-6">Choisissez le champ à configurer.</p>

    <div class="columns is-multiline">
        <div class="column is-3">
            <a href="{{ route('reference-data.vehicles.index') }}" class="box has-text-centered">
                <p class="title is-4">&#128663;</p>
                <p class="title is-5">Véhicules</p>
                <p class="has-text-grey">{{ $vehiclesCount }} enregistré(s)</p>
            </a>
        </div>
        <div class="column is-3">
            <a href="{{ route('reference-data.locations.index') }}" class="box has-text-centered">
                <p class="title is-4">&#128205;</p>
                <p class="title is-5">Localisation des bornes</p>
                <p class="has-text-grey">{{ $locationsCount }} enregistrée(s)</p>
            </a>
        </div>
        <div class="column is-3">
            <a href="{{ route('reference-data.providers.index') }}" class="box has-text-centered">
                <p class="title is-4">&#9889;</p>
                <p class="title is-5">Fournisseurs de bornes</p>
                <p class="has-text-grey">{{ $providersCount }} enregistré(s)</p>
            </a>
        </div>
        <div class="column is-3">
            <a href="{{ route('reference-data.power-ratings.index') }}" class="box has-text-centered">
                <p class="title is-4">&#128268;</p>
                <p class="title is-5">Puissances (kW)</p>
                <p class="has-text-grey">{{ $powerRatingsCount }} enregistrée(s)</p>
            </a>
        </div>
        <div class="column is-3">
            <a href="{{ route('reference-data.sms.index') }}" class="box has-text-centered">
                <p class="title is-4">&#128241;</p>
                <p class="title is-5">Envois SMS</p>
                <p class="has-text-grey">
                    {{ $smsCount }} envoi(s)
                    @if ($smsFailedCount > 0)
                        &middot; <span class="has-text-danger">{{ $smsFailedCount }} en échec</span>
                    @endif
                </p>
            </a>
        </div>
        <div class="column is-3">
            <a href="{{ route('reference-data.sources.index') }}" class="box has-text-centered">
                <p class="title is-4">&#128225;</p>
                <p class="title is-5">Données récupérées</p>
                <p class="has-text-grey">
                    {{ $dataSourceCount }} source(s) externe(s)
                    @if ($staleSourceCount > 0)
                        &middot; <span class="has-text-warning-dark">{{ $staleSourceCount }} en retard</span>
                    @endif
                </p>
            </a>
        </div>
        @if (\App\Support\CurrentUser::get()?->is_admin)
            <div class="column is-3">
                <a href="{{ route('reference-data.users.index') }}" class="box has-text-centered">
                    <p class="title is-4">&#128101;</p>
                    <p class="title is-5">Utilisateurs</p>
                    <p class="has-text-grey">
                        {{ \App\Models\User::count() }} compte(s)
                        @php($pending = \App\Models\User::whereNull('approved_at')->count())
                        @if ($pending > 0)
                            &middot; <span class="has-text-warning-dark">{{ $pending }} en attente</span>
                        @endif
                    </p>
                </a>
            </div>
        @endif
        <div class="column is-3">
            <a href="{{ route('account.index') }}" class="box has-text-centered">
                <p class="title is-4">&#128100;</p>
                <p class="title is-5">Mon compte</p>
                <p class="has-text-grey">{{ \App\Support\CurrentUser::get()?->email }}</p>
            </a>
        </div>
    </div>
@endsection
