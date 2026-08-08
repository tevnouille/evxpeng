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
                <p class="title is-5">Localisations</p>
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
    </div>
@endsection
