<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- Lu par les appels fetch (ajout/retrait d'une borne favorite). --}}
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'EV Recharges')</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('scripts')
</head>
<body>
    <nav class="navbar is-dark" role="navigation" aria-label="main navigation">
        <div class="navbar-brand">
            <a class="navbar-item" href="{{ route('charging-sessions.index') }}">
                <strong>&#9889; EV Recharges</strong>
            </a>
            <a role="button" class="navbar-burger" data-target="navMenu" aria-label="menu" aria-expanded="false" onclick="document.getElementById('navMenu').classList.toggle('is-active'); this.classList.toggle('is-active');">
                <span aria-hidden="true"></span>
                <span aria-hidden="true"></span>
                <span aria-hidden="true"></span>
            </a>
        </div>
        <div id="navMenu" class="navbar-menu">
            <div class="navbar-start">
                <a class="navbar-item {{ request()->routeIs('charging-sessions.*') ? 'is-active' : '' }}" href="{{ route('charging-sessions.index') }}">
                    @include('layouts._icon', ['name' => 'recharges'])Recharges
                </a>
                <div class="navbar-item has-dropdown is-hoverable">
                    <a class="navbar-link {{ request()->routeIs('history.*') || request()->routeIs('dashboard') ? 'is-active' : '' }}"
                       href="{{ route('history.index') }}">
                        @include('layouts._icon', ['name' => 'suivi'])Suivi
                    </a>
                    <div class="navbar-dropdown">
                        <a class="navbar-item {{ request()->routeIs('history.*') ? 'is-active' : '' }}" href="{{ route('history.index') }}">
                            @include('layouts._icon', ['name' => 'historique'])Historique
                        </a>
                        <a class="navbar-item {{ request()->routeIs('dashboard') ? 'is-active' : '' }}" href="{{ route('dashboard') }}">
                            @include('layouts._icon', ['name' => 'dashboard'])Dashboard
                        </a>
                    </div>
                </div>
                @if (\App\Support\CurrentUser::get()?->hasTelemetry())
                    <div class="navbar-item has-dropdown is-hoverable">
                        <a class="navbar-link {{ request()->routeIs('my-vehicle.*') ? 'is-active' : '' }}"
                           href="{{ route('my-vehicle.index') }}">
                            @include('layouts._icon', ['name' => 'voiture'])Ma voiture
                        </a>
                        <div class="navbar-dropdown">
                            <a class="navbar-item {{ request()->routeIs('my-vehicle.index') ? 'is-active' : '' }}" href="{{ route('my-vehicle.index') }}">
                                Vue d'ensemble
                            </a>
                            <a class="navbar-item {{ request()->routeIs('my-vehicle.obd') ? 'is-active' : '' }}" href="{{ route('my-vehicle.obd') }}">
                                Statistiques OBD
                            </a>
                            {{-- Adresse servie sans passkey : ouverte dans un
                                 onglet a part, pour ne pas donner a croire qu'on
                                 quitte la session, et signalee comme publique. --}}
                            <a class="navbar-item" href="{{ route('info-car') }}" target="_blank" rel="noopener">
                                Écran voiture
                                <span class="tag is-warning is-light ml-2">public</span>
                            </a>
                        </div>
                    </div>
                @endif
                <div class="navbar-item has-dropdown is-hoverable">
                    <a class="navbar-link {{ request()->routeIs('charging-curves.*') ? 'is-active' : '' }}"
                       href="{{ route('charging-curves.index') }}">
                        @include('layouts._icon', ['name' => 'courbe'])Courbe de recharge
                    </a>
                    <div class="navbar-dropdown">
                        <a class="navbar-item {{ request()->routeIs('charging-curves.index') ? 'is-active' : '' }}" href="{{ route('charging-curves.index') }}">
                            Courbe du véhicule
                        </a>
                        <a class="navbar-item {{ request()->routeIs('charging-curves.compare') ? 'is-active' : '' }}" href="{{ route('charging-curves.compare') }}">
                            Recharges face à la courbe
                        </a>
                    </div>
                </div>
                <div class="navbar-item has-dropdown is-hoverable">
                    <a class="navbar-link {{ request()->routeIs('trips.*') || request()->routeIs('planner.*') || request()->routeIs('favorites.*') || request()->routeIs('position-shares.*') || request()->routeIs('station-notes.*') ? 'is-active' : '' }}"
                       href="{{ route('favorites.index') }}">
                        @include('layouts._icon', ['name' => 'trajets'])Trajets
                    </a>
                    <div class="navbar-dropdown">
                        @if (\App\Support\CurrentUser::get()?->hasTelemetry())
                            <a class="navbar-item {{ request()->routeIs('trips.index') ? 'is-active' : '' }}" href="{{ route('trips.index') }}">
                                @include('layouts._icon', ['name' => 'carte'])Déplacements
                            </a>
                            <a class="navbar-item {{ request()->routeIs('trips.recurring') ? 'is-active' : '' }}" href="{{ route('trips.recurring') }}">
                                @include('layouts._icon', ['name' => 'carte'])Trajets habituels
                            </a>
                            <a class="navbar-item {{ request()->routeIs('position-shares.*') ? 'is-active' : '' }}" href="{{ route('position-shares.index') }}">
                                @include('layouts._icon', ['name' => 'carte'])Partager ma position
                            </a>
                        @endif
                        <a class="navbar-item {{ request()->routeIs('planner.*') ? 'is-active' : '' }}" href="{{ route('planner.index') }}">
                            @include('layouts._icon', ['name' => 'planificateur'])Planificateur
                        </a>
                        <a class="navbar-item {{ request()->routeIs('favorites.*') ? 'is-active' : '' }}" href="{{ route('favorites.index') }}">
                            @include('layouts._icon', ['name' => 'favoris'])Favoris
                        </a>
                        <a class="navbar-item {{ request()->routeIs('station-notes.*') ? 'is-active' : '' }}" href="{{ route('station-notes.index') }}">
                            @include('layouts._icon', ['name' => 'favoris'])Notes sur les bornes
                        </a>
                    </div>
                </div>
                <a class="navbar-item {{ request()->routeIs('reference-data.*') ? 'is-active' : '' }}" href="{{ route('reference-data.index') }}">
                    @include('layouts._icon', ['name' => 'administration'])Administration
                </a>
            </div>

            <div class="navbar-end">
                <a class="navbar-item {{ request()->routeIs('changelog') ? 'is-active' : '' }}" href="{{ route('changelog') }}">
                    @include('layouts._icon', ['name' => 'changelog'])Changelog
                </a>
                @if ($user = \App\Support\CurrentUser::get())
                    <a class="navbar-item {{ request()->routeIs('account.*') ? 'is-active' : '' }}" href="{{ route('account.index') }}">
                        @include('layouts._icon', ['name' => 'compte']){{ $user->email }}
                    </a>
                    {{-- Deconnexion de la passerelle, donc de tous les services
                         qu'elle protege. Le titre le dit au survol ; « Mon
                         compte » l'explique en toutes lettres. --}}
                    <a class="navbar-item" href="{{ route('logout') }}"
                       title="Déconnecte de la passerelle passkey, donc de tous les services qu'elle protège">
                        @include('layouts._icon', ['name' => 'deconnexion'])Déconnexion
                    </a>
                @endif
            </div>
        </div>
    </nav>

    <section class="section pt-3">
        <div class="container">
            @if (session('success'))
                <div class="notification is-success is-light">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="notification is-danger is-light">{{ session('error') }}</div>
            @endif
            @if ($errors->any())
                <div class="notification is-danger is-light">
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </div>
    </section>
</body>
</html>
