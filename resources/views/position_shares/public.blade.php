<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Position de {{ $vehicle->name }}</title>
    {{-- Domaine dedie, entierement public (docker/share/README.md) : a la
         difference d'/infoCar, les assets du site sont ici accessibles sans
         passkey, on peut donc s'appuyer sur le meme Bulma que le reste. --}}
    @vite(['resources/css/app.css', 'resources/js/position-share.js'])
</head>
<body>
    <section class="section pt-4">
        <div class="container">
            <h1 class="title is-4">Position de {{ $vehicle->name }}</h1>
            <p class="subtitle is-6">
                Partage valable jusqu'au {{ $share->expires_at->format('d/m/Y à H:i') }}.
                <span id="share-refresh-status" class="has-text-grey"></span>
            </p>

            @if ($mapPoints->isEmpty())
                <div class="notification is-warning is-light">
                    Aucun relevé de position depuis le début du partage. Revenez un peu plus tard.
                </div>
            @else
                <div id="share-map" style="height: 70vh;" data-points='@json($mapPoints)'></div>
                <p class="has-text-grey is-size-7 mt-3">
                    Point rouge : position la plus récente. La ligne est pointillée à dessein : elle relie
                    des relevés espacés d'une minute au mieux, elle ne retrace pas la route empruntée.
                </p>
            @endif
        </div>
    </section>
</body>
</html>
