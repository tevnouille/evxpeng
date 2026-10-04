<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Accès véhicule</title>
    {{-- Meme parti pris que la page qu'elle protege : aucune feuille de style
         ni script externe, tout tient dans une seule reponse. --}}
    <style>
        :root { color-scheme: light dark; }
        * { box-sizing: border-box; }
        html, body { height: 100%; }
        body {
            margin: 0; padding: 4vh 6vw;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            overflow: hidden;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            background: #fff; color: #1a1a1a;
        }
        h1 { font-size: clamp(1rem, 4vh, 1.6rem); margin: 0 0 3vh; text-align: center; }
        .erreur {
            color: #c0392b; font-weight: 600; margin: 0 0 2vh;
            font-size: clamp(.8rem, 2.2vh, 1rem); text-align: center;
        }
        .cases { display: flex; gap: 2.5vw; margin-bottom: 4vh; }
        .case {
            width: clamp(1.8rem, 8vw, 3rem); height: clamp(2.2rem, 10vh, 3.6rem);
            border: 2px solid #d0d0d0; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: clamp(1.2rem, 5vh, 2rem); font-weight: 700;
        }
        .case.rempli { border-color: #2ea36b; }
        /* Cibles tactiles larges : on les vise d'un doigt, sur un ecran de bord. */
        .pave {
            display: grid; grid-template-columns: repeat(3, 1fr);
            gap: 2vh 3vw; width: min(80vw, 360px); flex: 0 0 auto;
        }
        .pave button {
            font: inherit; font-size: clamp(1.1rem, 4.5vh, 1.8rem); font-weight: 600;
            padding: 1.1em 0; border: 1px solid #d0d0d0; border-radius: 12px;
            background: #f4f4f4; color: #1a1a1a; cursor: pointer;
        }
        .pave button:active { background: #e0e0e0; }
        .pave button.vide { background: transparent; border-color: transparent; cursor: default; }
        @media (prefers-color-scheme: dark) {
            body { background: #16181c; color: #f0f0f0; }
            .case { border-color: #3a3f47; }
            .pave button { background: #24272d; border-color: #3a3f47; color: #f0f0f0; }
            .pave button:active { background: #2f333a; }
        }
    </style>
</head>
<body>
    <h1>Code d'accès</h1>

    @if (session('error'))
        <p class="erreur">{{ session('error') }}</p>
    @endif

    {{-- Formulaire soumis par script (les six chiffres saisis) ; il fonctionne
         aussi sans JavaScript si le champ recoit le focus et un clavier
         physique, auquel cas seul le bouton "Effacer" reste inoperant. --}}
    <form method="POST" action="{{ route('info-car.unlock') }}" id="form-code">
        @csrf
        <input type="hidden" name="code" id="champ-code" value="">
    </form>

    <div class="cases" id="cases" aria-hidden="true">
        <div class="case"></div>
        <div class="case"></div>
        <div class="case"></div>
        <div class="case"></div>
        <div class="case"></div>
        <div class="case"></div>
    </div>

    <div class="pave" role="group" aria-label="Pavé numérique">
        <button type="button" data-chiffre="1">1</button>
        <button type="button" data-chiffre="2">2</button>
        <button type="button" data-chiffre="3">3</button>
        <button type="button" data-chiffre="4">4</button>
        <button type="button" data-chiffre="5">5</button>
        <button type="button" data-chiffre="6">6</button>
        <button type="button" data-chiffre="7">7</button>
        <button type="button" data-chiffre="8">8</button>
        <button type="button" data-chiffre="9">9</button>
        <button type="button" class="vide" tabindex="-1" aria-hidden="true"></button>
        <button type="button" data-chiffre="0">0</button>
        <button type="button" id="bouton-effacer" aria-label="Effacer le dernier chiffre">⌫</button>
    </div>

<script>
    (function () {
        var code = '';
        var cases = document.querySelectorAll('#cases .case');
        var champ = document.getElementById('champ-code');
        var formulaire = document.getElementById('form-code');

        function rafraichir() {
            for (var i = 0; i < cases.length; i++) {
                var rempli = i < code.length;
                cases[i].textContent = rempli ? '•' : '';
                cases[i].classList.toggle('rempli', rempli);
            }
        }

        function ajouter(chiffre) {
            if (code.length >= 6) { return; }

            code += chiffre;
            rafraichir();

            // Soumission automatique au sixieme chiffre : sur un pave qu'on vise
            // du doigt, un bouton "Valider" de plus n'aurait rien apporte.
            if (code.length === 6) {
                champ.value = code;
                formulaire.submit();
            }
        }

        function effacer() {
            code = code.slice(0, -1);
            rafraichir();
        }

        var boutons = document.querySelectorAll('.pave button[data-chiffre]');
        for (var i = 0; i < boutons.length; i++) {
            boutons[i].addEventListener('click', function (e) {
                ajouter(e.currentTarget.dataset.chiffre);
            });
        }

        document.getElementById('bouton-effacer').addEventListener('click', effacer);

        // Un clavier physique reste utilisable : test depuis un ordinateur, ou
        // tablette de bord avec clavier Bluetooth.
        document.addEventListener('keydown', function (e) {
            if (e.key >= '0' && e.key <= '9') { ajouter(e.key); }
            else if (e.key === 'Backspace') { effacer(); }
        });
    })();
</script>
</body>
</html>
