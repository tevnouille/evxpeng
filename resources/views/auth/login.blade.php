<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Connexion — EV Recharges</title>
    @vite(['resources/css/app.css'])
</head>
<body>
    <section class="section">
        <div class="container" style="max-width: 24rem;">
            <h1 class="title has-text-centered">EV Recharges</h1>

            @if (session('error'))
                <div class="notification is-danger is-light">{{ session('error') }}</div>
            @endif

            <form method="POST" action="{{ route('login.store') }}" class="box">
                @csrf
                <div class="field">
                    <label class="label" for="email">Email</label>
                    <input class="input @error('email') is-danger @enderror" type="email" id="email" name="email"
                           value="{{ old('email') }}" required autofocus autocomplete="username">
                    @error('email')
                        <p class="help is-danger">{{ $message }}</p>
                    @enderror
                </div>
                <div class="field">
                    <label class="label" for="password">Mot de passe</label>
                    <input class="input" type="password" id="password" name="password" required
                           autocomplete="current-password">
                </div>
                <div class="field">
                    <label class="checkbox">
                        <input type="checkbox" name="remember" value="1"> Rester connecté
                    </label>
                </div>
                <button class="button is-link is-fullwidth" type="submit">Se connecter</button>
            </form>
        </div>
    </section>
</body>
</html>
