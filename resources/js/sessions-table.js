// Tri et filtre du tableau des recharges, cote navigateur.
//
// Un mois compte quelques dizaines de lignes, l'annee quelques centaines : tout
// est deja dans la page. Passer par le serveur pour trier ajouterait un
// aller-retour et ferait perdre la position de lecture, sans rien apporter.
//
// Le tableau est un partiel partage entre /recharges et l'historique d'un mois :
// ce fichier vaut donc pour les deux.

/** Valeur de tri d'une cellule : `data-value` si presente, sinon son texte. */
function valeur(cellule) {
    if (!cellule) {
        return '';
    }

    const brute = cellule.dataset.value;

    return brute !== undefined ? brute : cellule.textContent.trim();
}

/** Comparaison numerique quand les deux valeurs le sont, alphabetique sinon. */
function comparer(a, b) {
    const na = parseFloat(a);
    const nb = parseFloat(b);
    const numerique = a !== '' && b !== '' && !isNaN(na) && !isNaN(nb)
        && String(na) === a.trim() && String(nb) === b.trim();

    if (numerique) {
        return na - nb;
    }

    return a.localeCompare(b, 'fr', { numeric: true, sensitivity: 'base' });
}

/** Minuscules sans accents : « Crécy » doit se trouver en tapant « crecy ». */
function aplatir(texte) {
    return texte.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
}

function setUpTable(table) {
    const corps = table.tBodies[0];
    const entetes = [...table.tHead.rows[0].cells];

    // La ligne « aucune recharge » porte un colspan : elle n'est ni triable ni
    // filtrable, et doit rester visible quand le tableau est vide.
    const lignesDe = () => [...corps.rows].filter((r) => !r.cells[0]?.hasAttribute('colspan'));

    if (lignesDe().length === 0) {
        return;
    }

    let colonneTriee = null;
    let ordre = 1;

    entetes.forEach((entete, index) => {
        if (entete.dataset.sort === undefined) {
            return;
        }

        entete.classList.add('is-sortable');
        entete.setAttribute('role', 'button');
        entete.setAttribute('tabindex', '0');

        const trier = () => {
            ordre = colonneTriee === index ? -ordre : 1;
            colonneTriee = index;

            entetes.forEach((e) => e.removeAttribute('data-direction'));
            entete.setAttribute('data-direction', ordre === 1 ? 'asc' : 'desc');
            entete.setAttribute('aria-sort', ordre === 1 ? 'ascending' : 'descending');

            const lignes = lignesDe();
            lignes.sort((a, b) => ordre * comparer(valeur(a.cells[index]), valeur(b.cells[index])));
            lignes.forEach((ligne) => corps.appendChild(ligne));
        };

        entete.addEventListener('click', trier);
        entete.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                trier();
            }
        });
    });

    const champ = document.querySelector(`[data-filters="${table.id}"]`);
    const compteur = document.querySelector(`[data-filter-count="${table.id}"]`);

    if (!champ) {
        return;
    }

    const filtrer = () => {
        const terme = aplatir(champ.value.trim());
        let visibles = 0;

        lignesDe().forEach((ligne) => {
            // Les colonnes a exclure sont marquees, jamais deduites de leur
            // position : la derniere colonne porte des boutons sur le tableau
            // des recharges — ou « supprimer » aurait correspondu a toutes les
            // lignes — mais l'adresse sur celui des releves, qu'on veut filtrer.
            const texte = aplatir(
                [...ligne.cells]
                    .filter((c) => !c.hasAttribute('data-nofilter'))
                    .map((c) => c.textContent)
                    .join(' ')
            );
            const garde = terme === '' || texte.includes(terme);

            ligne.hidden = !garde;

            if (garde) {
                visibles++;
            }
        });

        if (compteur) {
            const total = lignesDe().length;
            // Le libelle vient du tableau : le meme mecanisme sert aux recharges
            // et aux positions, et « 4 recharge(s) » sous une liste de releves
            // serait faux.
            const unite = table.dataset.unit || 'ligne(s)';
            compteur.textContent = terme === ''
                ? `${total} ${unite}`
                : `${visibles} ${unite} sur ${total}`;
        }
    };

    champ.addEventListener('input', filtrer);
    filtrer();
}

document.querySelectorAll('table[data-sessions-table]').forEach(setUpTable);
