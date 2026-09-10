<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Partage expiré</title>
    @vite(['resources/css/app.css'])
</head>
<body>
    <section class="section">
        <div class="container">
            <div class="notification is-warning is-light">
                <h1 class="title is-5">Ce lien de partage a expiré</h1>
                <p>
                    Il n'était valable que jusqu'au {{ $share->expires_at->format('d/m/Y à H:i') }}.
                    Demandez un nouveau lien à la personne qui vous l'a envoyé.
                </p>
            </div>
        </div>
    </section>
</body>
</html>
