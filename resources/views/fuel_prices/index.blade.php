@extends('layouts.app')

@section('title', 'Prix carburants')

@section('content')
    <a href="{{ route('dashboard') }}" class="is-size-7">&larr; Dashboard</a>
    <h1 class="title mt-2">Historique des prix carburants</h1>
    <p class="subtitle is-6">
        Moyenne nationale quotidienne (SP95 / Gazole). Historique depuis le 1er janvier reconstruit à partir de
        l'archive officielle <a href="https://donnees.roulez-eco.fr" target="_blank" rel="noopener">donnees.roulez-eco.fr</a>
        (<code>php artisan fuel-prices:backfill</code>) ; le jour courant est relevé en direct via l'API
        <a href="https://data.economie.gouv.fr" target="_blank" rel="noopener">data.economie.gouv.fr</a> à chaque visite du dashboard.
    </p>

    <div class="table-container">
        <table class="table is-fullwidth is-striped">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Essence (SP95) €/L</th>
                    <th>Diesel (Gazole) €/L</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($prices as $price)
                    <tr>
                        <td>{{ $price->date->format('d/m/Y') }}</td>
                        <td>{{ number_format((float) $price->essence_price, 3, ',', ' ') }}</td>
                        <td>{{ number_format((float) $price->diesel_price, 3, ',', ' ') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="has-text-grey">Aucun relevé pour l'instant — revenez après avoir consulté le dashboard.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
