#!/usr/bin/env python3
"""
Applique la mise a jour d'un paquet demandee depuis la page d'administration.

Deux ecosystemes seulement, ceux dont la mise a jour reste rattrapable :

  npm      met a jour node_modules et reconstruit les fichiers du navigateur ;
           si la construction casse, l'etat precedent est restaure.
  apt      met a jour un seul paquet systeme, sans toucher aux autres.
  composer met a jour le verrou, reconstruit l'image et recree le conteneur.
           C'est un deploiement : le site repond mal quelques secondes. Si
           l'image ne se construit pas, ou si le site ne repond plus apres le
           redemarrage, le verrou precedent est remis et l'image refaite avec.

Seules les montees jugees compatibles sont acceptees, et le verdict est
recalcule ici a partir du releve : une demande ne peut pas se declarer
elle-meme inoffensive.
"""

import json
import os
import shutil
import subprocess
import sys
import time
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
SYSTEM = ROOT / 'storage' / 'app' / 'system'
REQUEST = SYSTEM / 'server-update.request'
LOG = SYSTEM / 'server-update.log'
LOCK = SYSTEM / 'server-update.lock'
INVENTORY = SYSTEM / 'server-inventory.json'
BACKUP = SYSTEM / 'backup'

STEP_TIMEOUT = 600
# Une reconstruction d'image telecharge ses dependances : plus long.
BUILD_TIMEOUT = 900
MAX_LOG_ENTRIES = 40

NODE_IMAGE = 'node:22-alpine'
COMPOSER_IMAGE = 'composer:latest'
HEALTH_URL = 'http://ev-nginx/recharges'


def now():
    return datetime.now(timezone.utc).isoformat(timespec='seconds')


def run(cmd, timeout=STEP_TIMEOUT, env=None):
    """(succes, sortie) — stdout et stderr melanges, pour le journal."""
    try:
        out = subprocess.run(
            cmd, capture_output=True, text=True, timeout=timeout,
            check=False, cwd=str(ROOT), env=env,
        )
    except subprocess.TimeoutExpired:
        return False, f'Delai depasse ({timeout} s).'
    except (FileNotFoundError, OSError) as exc:
        return False, str(exc)

    return out.returncode == 0, (out.stdout + out.stderr).strip()


def node(*args, timeout=STEP_TIMEOUT):
    """Commande npm, dans un conteneur jetable : npm n'est pas installe ici."""
    # L'uid emprunte n'a pas de repertoire personnel dans l'image : sans ces
    # deux variables, npm tente d'ecrire son cache dans / et echoue.
    return run([
        'sudo', '-n', 'docker', 'run', '--rm',
        '-v', f'{ROOT}:/app', '-w', '/app',
        '-u', f'{os.getuid()}:{os.getgid()}',
        '-e', 'HOME=/tmp',
        '-e', 'npm_config_cache=/tmp/.npm',
        NODE_IMAGE, *args,
    ], timeout=timeout)


def journal(entry):
    entries = []
    if LOG.exists():
        try:
            entries = json.loads(LOG.read_text())
        except (OSError, json.JSONDecodeError):
            entries = []

    entries.insert(0, entry)
    LOG.write_text(json.dumps(entries[:MAX_LOG_ENTRIES], ensure_ascii=False, indent=1))
    os.chmod(LOG, 0o664)


def allowed(ecosystem, package):
    """
    Le paquet est-il bien une montee compatible ?

    On relit le releve plutot que de croire la demande : c'est ce qui empeche
    une requete forgee de faire passer un changement de version majeure.
    """
    try:
        inventory = json.loads(INVENTORY.read_text())
    except (OSError, json.JSONDecodeError):
        return False, "Aucun releve disponible : lancez d'abord une verification."

    for row in inventory.get(ecosystem, []):
        if row['name'] != package:
            continue

        if ecosystem == 'npm':
            if row.get('direct') and row.get('wanted') != row.get('version'):
                return True, row['wanted']
            return False, "Montee non compatible, ou paquet tire par une autre dependance."

        if ecosystem == 'composer':
            if row.get('status') == 'semver-safe-update':
                return True, row['available']
            return False, "Montee non compatible : version majeure, ou paquet absent du conteneur."

        if row.get('outdated'):
            return True, row['available']
        return False, 'Ce paquet est deja a jour.'

    return False, 'Paquet introuvable dans le releve.'


def all_eligible():
    """
    Tous les paquets dont la montee est compatible, par ecosysteme.

    Sert au traitement groupe : une seule commande par ecosysteme, donc une
    seule reconstruction d'image au lieu d'une par paquet.

    @return dict[str, list[tuple[str, str]]] ecosysteme -> [(nom, cible)]
    """
    try:
        inventory = json.loads(INVENTORY.read_text())
    except (OSError, json.JSONDecodeError):
        return {}

    eligible = {}
    for ecosystem in HANDLERS:
        rows = []
        for row in inventory.get(ecosystem, []):
            permitted, detail = allowed(ecosystem, row['name'])
            if permitted:
                rows.append((row['name'], detail))
        if rows:
            eligible[ecosystem] = rows

    return eligible


def as_specs(value):
    """Un paquet seul ou une liste : les deux appels partagent le meme code."""
    return value if isinstance(value, list) else [value]


def update_npm(package, target):
    """Montee dans la contrainte declaree, puis reconstruction des assets."""
    specs = as_specs(package) if target is None else [f'{package}@{target}']
    BACKUP.mkdir(parents=True, exist_ok=True)
    saved = []
    for name in ('package.json', 'package-lock.json'):
        src = ROOT / name
        if src.exists():
            dst = BACKUP / name
            shutil.copy2(src, dst)
            saved.append((src, dst))

    def rollback():
        for src, dst in saved:
            shutil.copy2(dst, src)

    ok, out = node('npm', 'install', *specs, '--no-audit', '--no-fund')
    if not ok:
        rollback()
        return False, f'npm install a echoue :\n{out[-1200:]}'

    ok, build = node('npm', 'run', 'build')
    if not ok:
        # Les fichiers du navigateur seraient incoherents : on remet tout.
        rollback()
        node('npm', 'ci', '--no-audit', '--no-fund')
        node('npm', 'run', 'build')
        return False, f"Construction en echec, etat precedent restaure :\n{build[-1200:]}"

    return True, (f'Mis a jour en {target}, fichiers du navigateur reconstruits.' if target
                  else f'{len(specs)} paquet(s) montes, fichiers du navigateur reconstruits.')


def site_answers():
    """
    Le site repond-il encore ?

    Interroge depuis le reseau des conteneurs, avec plusieurs tentatives : au
    redemarrage php-fpm met quelques secondes a accepter la premiere requete.
    """
    for _ in range(10):
        ok, out = run([
            'sudo', '-n', 'docker', 'run', '--rm', '--network', 'ev-net',
            'curlimages/curl', '-s', '-o', '/dev/null', '-w', '%{http_code}',
            '-H', 'X-SSO-Email: atran@lolinux.org', HEALTH_URL,
        ], timeout=60)
        if ok and out.strip() == '200':
            return True
        time.sleep(6)

    return False


def update_composer(package, target):
    """
    Monte le verrou, reconstruit l'image, recree le conteneur.

    Composer n'est pas installe sur l'hote : il tourne dans un conteneur
    jetable, qui ecrit le verrou du projet. Le vendor/ reellement servi est
    celui de l'image, d'ou la reconstruction — et donc le controle du site
    apres coup, seul moyen de savoir si la montee a casse quelque chose.
    """
    BACKUP.mkdir(parents=True, exist_ok=True)
    saved = []
    for name in ('composer.json', 'composer.lock'):
        src = ROOT / name
        if src.exists():
            dst = BACKUP / name
            shutil.copy2(src, dst)
            saved.append((src, dst))

    def rollback():
        for src, dst in saved:
            shutil.copy2(dst, src)

    names = as_specs(package)
    ok, out = run([
        'sudo', '-n', 'docker', 'run', '--rm',
        '-v', f'{ROOT}:/app', '-w', '/app',
        '-u', f'{os.getuid()}:{os.getgid()}',
        '-e', 'COMPOSER_HOME=/tmp/composer',
        COMPOSER_IMAGE, 'update', *names, '--with-dependencies',
        '--no-interaction', '--no-scripts',
    ], timeout=BUILD_TIMEOUT)
    if not ok:
        rollback()
        return False, f'composer update a echoue :\n{out[-1200:]}'

    ok, build = run(['docker-compose', 'build', 'app'], timeout=BUILD_TIMEOUT)
    if not ok:
        rollback()
        return False, f"Reconstruction de l'image en echec, verrou precedent remis :\n{build[-1200:]}"

    run(['docker-compose', 'up', '-d', 'app'], timeout=300)

    if not site_answers():
        # Le deploiement a casse le site : retour a l'etat d'avant.
        rollback()
        run(['docker-compose', 'build', 'app'], timeout=BUILD_TIMEOUT)
        run(['docker-compose', 'up', '-d', 'app'], timeout=300)
        return False, ("Le site ne repondait plus apres le deploiement : "
                       "la version precedente a ete remise en place.")

    return True, (f'Mis a jour en {target}, image reconstruite et conteneur redemarre.' if target
                  else f'{len(names)} paquet(s) montes, image reconstruite et conteneur redemarre.')


def update_apt(package, target):
    """Un seul paquet, sans embarquer le reste du systeme."""
    names = as_specs(package)
    env = dict(os.environ, DEBIAN_FRONTEND='noninteractive')
    ok, out = run(
        ['sudo', '-n', 'apt-get', 'install', '--only-upgrade', '-y', *names],
        env=env,
    )
    if not ok:
        return False, f'apt-get a echoue :\n{out[-1200:]}'

    return True, (f'Mis a jour en {target}.' if target
                  else f'{len(names)} paquet(s) systeme mis a jour.')


def update_npm_batch(pairs):
    """Une seule installation puis une seule construction, pour tout le lot."""
    return update_npm([f'{name}@{target}' for name, target in pairs], None)


def update_apt_batch(pairs):
    return update_apt([name for name, _ in pairs], None)


def update_composer_batch(pairs):
    """Un seul composer update, donc une seule reconstruction d'image."""
    return update_composer([name for name, _ in pairs], None)


HANDLERS = {'npm': update_npm, 'apt': update_apt, 'composer': update_composer}
BATCH_HANDLERS = {'npm': update_npm_batch, 'apt': update_apt_batch, 'composer': update_composer_batch}


def update_everything():
    """
    Toutes les montees compatibles, groupees par ecosysteme.

    Un appel par ecosysteme plutot qu'un par paquet : sans cela, monter les
    vingt-six dependances PHP reconstruirait l'image vingt-six fois. Chaque
    ecosysteme garde son propre filet, et un echec n'empeche pas les autres
    d'aboutir — le compte rendu dit lesquels sont passes.
    """
    eligible = all_eligible()

    if not eligible:
        return True, 'Rien a mettre a jour : tout est deja a jour.'

    resultats = []
    echec = False

    # Composer en dernier : c'est lui qui redemarre le conteneur, autant que
    # le reste soit deja en place quand le site repart.
    ordre = [e for e in ('apt', 'npm', 'composer') if e in eligible]

    for ecosystem in ordre:
        pairs = eligible[ecosystem]
        ok, message = BATCH_HANDLERS[ecosystem](pairs)
        echec = echec or not ok
        resultats.append(f"{ecosystem} ({len(pairs)}) : {'ok' if ok else 'echec'} — {message}")

    return not echec, '\n'.join(resultats)


def main():
    if not REQUEST.exists():
        return 0

    # Une seule operation a la fois.
    if LOCK.exists() and time.time() - LOCK.stat().st_mtime < STEP_TIMEOUT:
        return 0

    try:
        demand = json.loads(REQUEST.read_text())
    except (OSError, json.JSONDecodeError):
        REQUEST.unlink(missing_ok=True)
        return 1

    REQUEST.unlink(missing_ok=True)
    LOCK.touch()

    ecosystem = demand.get('ecosystem')
    package = demand.get('package')
    entry = {'at': now(), 'ecosystem': ecosystem, 'package': package,
             'success': False, 'message': ''}

    try:
        if demand.get('mode') == 'all':
            entry['package'] = 'toutes les montees compatibles'
            entry['ecosystem'] = 'tous'
            entry['success'], entry['message'] = update_everything()
        elif ecosystem not in HANDLERS or not package:
            entry['message'] = 'Demande incomprehensible.'
        else:
            permitted, detail = allowed(ecosystem, package)
            if not permitted:
                entry['message'] = detail
            else:
                entry['target'] = detail
                entry['success'], entry['message'] = HANDLERS[ecosystem](package, detail)
    except Exception as exc:  # noqa: BLE001 — le journal doit tout capter
        entry['message'] = f'Erreur inattendue : {exc}'
    finally:
        journal(entry)
        LOCK.unlink(missing_ok=True)

    # Le releve doit refleter le nouvel etat, succes ou echec.
    run(['/usr/bin/python3', str(ROOT / 'scripts' / 'server-inventory.py')])

    print(f"{ecosystem}/{package} : {'ok' if entry['success'] else 'echec'} — {entry['message'][:120]}")

    return 0 if entry['success'] else 1


if __name__ == '__main__':
    sys.exit(main())
