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
                <a class="navbar-item {{ request()->routeIs('history.*') ? 'is-active' : '' }}" href="{{ route('history.index') }}">
                    @include('layouts._icon', ['name' => 'historique'])Historique
                </a>
                <a class="navbar-item {{ request()->routeIs('dashboard') ? 'is-active' : '' }}" href="{{ route('dashboard') }}">
                    @include('layouts._icon', ['name' => 'dashboard'])Dashboard
                </a>
                <a class="navbar-item {{ request()->routeIs('my-vehicle.*') ? 'is-active' : '' }}" href="{{ route('my-vehicle.index') }}">
                    @include('layouts._icon', ['name' => 'voiture'])Ma voiture
                </a>
                <a class="navbar-item {{ request()->routeIs('charging-curves.*') ? 'is-active' : '' }}" href="{{ route('charging-curves.index') }}">
                    @include('layouts._icon', ['name' => 'courbe'])Courbe de recharge
                </a>
                <a class="navbar-item {{ request()->routeIs('trips.*') ? 'is-active' : '' }}" href="{{ route('trips.index') }}">
                    @include('layouts._icon', ['name' => 'carte'])Déplacements
                </a>
                <a class="navbar-item {{ request()->routeIs('planner.*') ? 'is-active' : '' }}" href="{{ route('planner.index') }}">
                    @include('layouts._icon', ['name' => 'planificateur'])Planificateur
                </a>
                <a class="navbar-item {{ request()->routeIs('favorites.*') ? 'is-active' : '' }}" href="{{ route('favorites.index') }}">
                    @include('layouts._icon', ['name' => 'favoris'])Favoris
                </a>
                <a class="navbar-item {{ request()->routeIs('reference-data.*') ? 'is-active' : '' }}" href="{{ route('reference-data.index') }}">
                    @include('layouts._icon', ['name' => 'administration'])Administration
                </a>
            </div>

            <div class="navbar-end">
                @if ($user = \App\Support\CurrentUser::get())
                    <a class="navbar-item {{ request()->routeIs('account.*') ? 'is-active' : '' }}" href="{{ route('account.index') }}">
                        @include('layouts._icon', ['name' => 'compte']){{ $user->email }}
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
