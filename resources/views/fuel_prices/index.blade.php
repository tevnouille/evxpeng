@extends('layouts.app')

@section('title', 'Prix carburants')

@section('content')
    <a href="{{ route('dashboard') }}" class="is-size-7">&larr; Dashboard</a>
    <h1 class="title mt-2">Historique des prix carburants</h1>
    <p class="subtitle is-6">
        Moyenne nationale instantanée (SP95 / Gazole), relevée une fois par jour via l'API ouverte
        <a href="https://data.economie.gouv.fr" target="_blank" rel="noopener">data.economie.gouv.fr</a>.
        Pas d'historique avant la première visite du dashboard un jour donné.
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
