@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <h1 class="title">Dashboard</h1>

    @if ($lifetime['sessions_count'] > 0)
        <div class="box">
            <h2 class="title is-6 mb-2">
                Depuis le début
                @if ($lifetime['first_date'])
                    <span class="has-text-grey has-text-weight-normal is-size-7">
                        (première recharge le {{ \Illuminate\Support\Carbon::parse($lifetime['first_date'])->format('d/m/Y') }})
                    </span>
                @endif
            </h2>
            <div class="columns is-mobile is-multiline mb-1">
                <div class="column">
                    <p class="heading">Recharges</p>
                    <p class="title is-4">{{ $lifetime['sessions_count'] }}</p>
                </div>
                <div class="column">
                    <p class="heading">Énergie</p>
                    <p class="title is-4">{{ number_format($lifetime['kwh'], 0, ',', ' ') }} kWh</p>
                </div>
                <div class="column">
                    <p class="heading">Payé</p>
                    <p class="title is-4">{{ number_format($lifetime['cost'], 2, ',', ' ') }} €</p>
                </div>
                <div class="column">
                    <p class="heading">{{ $lifetime['gain'] >= 0 ? 'Gain vs valeur réelle' : 'Surcoût vs valeur réelle' }}</p>
                    <p class="title is-4">{{ number_format(abs($lifetime['gain']), 2, ',', ' ') }} €</p>
                </div>
            </div>

            @if ($showFuelEquivalent)
                <div class="columns is-mobile is-multiline mb-1">
                    <div class="column">
                        <p class="heading">{{ $lifetime['savings_essence'] >= 0 ? 'Économie vs essence' : 'Surcoût vs essence' }}</p>
                        <p class="title is-4">{{ number_format(abs($lifetime['savings_essence']), 2, ',', ' ') }} €</p>
                    </div>
                    <div class="column">
                        <p class="heading">{{ $lifetime['savings_diesel'] >= 0 ? 'Économie vs diesel' : 'Surcoût vs diesel' }}</p>
                        <p class="title is-4">{{ number_format(abs($lifetime['savings_diesel']), 2, ',', ' ') }} €</p>
                    </div>
                </div>
                <p class="has-text-grey is-size-7">
                    D'après la consommation du véhicule et le prix du carburant du jour de chaque recharge
                    @if ($lifetime['configured_sessions'] < $lifetime['total_sessions'])
                        ({{ $lifetime['configured_sessions'] }}/{{ $lifetime['total_sessions'] }} recharge(s) concernée(s))
                    @endif
                    @if ($lifetime['estimated'])
                        — certaines recharges utilisent l'estimation par défaut (1,95 €/L), le prix du jour n'étant pas connu
                    @endif
                    . Réglable dans <a href="{{ route('account.index') }}">Mon compte</a>.
                </p>
            @endif
        </div>
    @endif

    <div id="ev-dashboard-root"
        data-api-url="{{ route('dashboard.data') }}"
        data-fuel-prices-url="{{ route('fuel-prices.index') }}"
        data-vehicles='@json($vehicles->map(fn ($v) => ["id" => $v->id, "name" => $v->name]))'
        data-years='@json($years)'
        data-current-year="{{ $currentYear }}"></div>
@endsection

@push('scripts')
    @vite('resources/js/dashboard.jsx')
@endpush
