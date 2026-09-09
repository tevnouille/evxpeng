{{--
    Page d'erreur qui se recharge d'elle-meme.

    Elle sert d'abord `/infoCar`, affichee sur l'ecran de la voiture : une page
    d'erreur y reste sinon jusqu'a ce qu'on la recharge a la main, ce qui ne se
    fait pas en conduisant. Trente secondes laissent au serveur le temps de
    revenir — un redemarrage de la machine, une limite de debit qui retombe —
    sans rien exiger du conducteur, et deux requetes par minute ne pesent rien.

    Aucun script ni feuille de style externe : cette page doit s'afficher meme
    quand l'application ne repond plus.
--}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="refresh" content="30">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('titre')</title>
    <style>
        :root { color-scheme: light dark; }
        body {
            margin: 0; min-height: 100vh;
            display: flex; flex-direction: column;
            align-items: center; justify-content: center; gap: .6em;
            padding: 2rem; text-align: center;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            background: #fff; color: #1a1a1a;
        }
        h1 { font-size: clamp(1.1rem, 5vh, 2rem); margin: 0; }
        p { margin: 0; font-size: clamp(.8rem, 2.6vh, 1.05rem); color: #6b6b6b; max-width: 34em; }
        @media (prefers-color-scheme: dark) {
            body { background: #16181c; color: #f0f0f0; }
            p { color: #a0a4ab; }
        }
    </style>
</head>
<body>
    <h1>@yield('titre')</h1>
    <p>@yield('explication')</p>
    <p>Cette page se recharge toute seule dans trente secondes.</p>
</body>
</html>
