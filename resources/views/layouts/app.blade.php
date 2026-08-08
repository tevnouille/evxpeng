<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'EV Recharges')</title>
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
                <a class="navbar-item {{ request()->routeIs('charging-sessions.*') ? 'is-active' : '' }}" href="{{ route('charging-sessions.index') }}">Recharges</a>
                <a class="navbar-item {{ request()->routeIs('dashboard') ? 'is-active' : '' }}" href="{{ route('dashboard') }}">Dashboard</a>
                <a class="navbar-item {{ request()->routeIs('reference-data.*') ? 'is-active' : '' }}" href="{{ route('reference-data.index') }}">Administration</a>
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
