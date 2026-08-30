#!/usr/bin/env python3
"""
Applique la mise a jour d'un paquet demandee depuis la page d'administration.

Deux ecosystemes seulement, ceux dont la mise a jour reste rattrapable :

  npm  met a jour node_modules et reconstruit les fichiers du navigateur ;
       si la construction casse, l'etat precedent est restaure.
  apt  met a jour un seul paquet systeme, sans toucher aux autres.

Composer en est volontairement exclu : mettre a jour une dependance PHP
suppose de reconstruire l'image et de recreer le conteneur, autrement dit un
deploiement, ce qui n'a pas sa place derriere un clic. Ces montees-la se font
a la main.

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
MAX_LOG_ENTRIES = 40

NODE_IMAGE = 'node:22-alpine'


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

        if row.get('outdated'):
            return True, row['available']
        return False, 'Ce paquet est deja a jour.'

    return False, 'Paquet introuvable dans le releve.'


def update_npm(package, target):
    """Montee dans la contrainte declaree, puis reconstruction des assets."""
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

    ok, out = node('npm', 'install', f'{package}@{target}', '--no-audit', '--no-fund')
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

    return True, f'Mis a jour en {target}, fichiers du navigateur reconstruits.'


def update_apt(package, target):
    """Un seul paquet, sans embarquer le reste du systeme."""
    env = dict(os.environ, DEBIAN_FRONTEND='noninteractive')
    ok, out = run(
        ['sudo', '-n', 'apt-get', 'install', '--only-upgrade', '-y', package],
        env=env,
    )
    if not ok:
        return False, f'apt-get a echoue :\n{out[-1200:]}'

    return True, f'Mis a jour en {target}.'


HANDLERS = {'npm': update_npm, 'apt': update_apt}


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
        if ecosystem not in HANDLERS or not package:
            entry['message'] = "Demande refusee : seuls npm et apt sont automatises."
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
