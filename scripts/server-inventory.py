#!/usr/bin/env python3
"""
Releve de l'etat du serveur et des dependances du projet.

Tourne sur l'hote, pas dans le conteneur : les paquets systeme et le
node_modules ne sont pas visibles depuis ev-app, et npm n'y est pas installe.
Le resultat est depose dans storage/app/, qui est monte en ecriture dans le
conteneur — c'est le seul chemin par lequel l'application peut le lire.

Usage :
    server-inventory.py              releve complet
    server-inventory.py --if-requested   ne fait rien sans fichier de demande
"""

import json
import os
import platform
import re
import shutil
import subprocess
import sys
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
OUTPUT = ROOT / 'storage' / 'app' / 'system' / 'server-inventory.json'
REQUEST_FLAG = ROOT / 'storage' / 'app' / 'system' / 'server-inventory.request'
DOC = Path('/usr/share/doc')

# Un releve complet fait quelques appels reseau : inutile de le refaire a chaque
# minute, le cron horaire suffit pour ce qui bouge ici.
TIMEOUT = 180


def run(cmd, timeout=TIMEOUT, ok_codes=(0,), stderr=False):
    """
    Sortie d'une commande, ou None si elle echoue — jamais d'exception.

    ok_codes existe pour `npm outdated`, qui sort en 1 des qu'il a quelque chose
    a signaler : son code de retour est un resultat, pas une erreur. stderr
    pour `nginx -v`, qui ecrit sa version la et pas sur la sortie standard.
    """
    try:
        out = subprocess.run(
            cmd, capture_output=True, text=True, timeout=timeout,
            check=False,
        )
    except (subprocess.TimeoutExpired, FileNotFoundError, OSError):
        return None

    if out.returncode not in ok_codes:
        return None

    return out.stderr if stderr else out.stdout


def run_json(cmd, timeout=TIMEOUT, ok_codes=(0,)):
    raw = run(cmd, timeout, ok_codes)
    if not raw:
        return None
    try:
        return json.loads(raw)
    except json.JSONDecodeError:
        return None


def host():
    """Caracteristiques de la machine."""
    info = {}

    os_release = {}
    try:
        for line in Path('/etc/os-release').read_text().splitlines():
            if '=' in line:
                k, v = line.split('=', 1)
                os_release[k] = v.strip('"')
    except OSError:
        pass

    info['os'] = os_release.get('PRETTY_NAME', platform.platform())
    info['kernel'] = platform.release()
    info['arch'] = platform.machine()
    info['hostname'] = platform.node()

    # /proc/cpuinfo donne le modele, nproc le nombre de coeurs utilisables.
    model = None
    try:
        for line in Path('/proc/cpuinfo').read_text().splitlines():
            if line.startswith('model name'):
                model = line.split(':', 1)[1].strip()
                break
    except OSError:
        pass
    info['cpu_model'] = model
    info['cpu_cores'] = os.cpu_count()

    mem = {}
    try:
        for line in Path('/proc/meminfo').read_text().splitlines():
            k, _, v = line.partition(':')
            mem[k] = int(v.strip().split()[0])
    except (OSError, ValueError):
        pass
    if mem:
        info['memory_total_mb'] = round(mem.get('MemTotal', 0) / 1024)
        info['memory_available_mb'] = round(mem.get('MemAvailable', 0) / 1024)

    try:
        usage = shutil.disk_usage('/')
        info['disk_total_gb'] = round(usage.total / 1024 ** 3, 1)
        info['disk_free_gb'] = round(usage.free / 1024 ** 3, 1)
    except OSError:
        pass

    try:
        uptime = float(Path('/proc/uptime').read_text().split()[0])
        info['uptime_days'] = round(uptime / 86400, 1)
    except (OSError, ValueError, IndexError):
        pass

    load = os.getloadavg() if hasattr(os, 'getloadavg') else None
    info['load'] = [round(v, 2) for v in load] if load else None

    docker = run(['docker', '--version'])
    info['docker'] = docker.strip() if docker else None

    return info


def containers():
    """Conteneurs du projet et image dont ils sont issus."""
    raw = run([
        'sudo', '-n', 'docker', 'ps', '--all', '--no-trunc',
        '--format', '{{.Names}}\t{{.Image}}\t{{.Status}}',
    ], timeout=30)
    if not raw:
        return []

    rows = []
    for line in raw.strip().splitlines():
        parts = line.split('\t')
        if len(parts) == 3 and parts[0].startswith('ev-'):
            rows.append({'name': parts[0], 'image': parts[1], 'status': parts[2]})

    return sorted(rows, key=lambda r: r['name'])


def dpkg_license(package):
    """
    Licence declaree dans le fichier de copyright Debian.

    Le format lisible par machine (DEP-5) porte des lignes "License:" ; environ
    un paquet sur six ne le suit pas, et on ne devine rien dans ce cas.
    """
    path = DOC / package / 'copyright'
    try:
        text = path.read_text(errors='replace')
    except OSError:
        return None

    licences = []
    for line in text.splitlines():
        if line.startswith('License:'):
            value = line.split(':', 1)[1].strip()
            if value and value not in licences:
                licences.append(value)
        if len(licences) >= 3:
            break

    return ', '.join(licences) if licences else None


def apt_packages():
    """Paquets systeme installes, avec ceux qui ont une mise a jour en attente."""
    # Rafraichit les index, sinon "version disponible" reflete le dernier
    # apt update, qui peut dater. L'echec n'est pas bloquant : on liste alors
    # ce qu'on sait deja.
    run(['sudo', '-n', 'apt-get', 'update', '-qq'], timeout=120)

    upgradable = {}
    raw = run(['apt', 'list', '--upgradable'], timeout=60) or ''
    for line in raw.splitlines():
        # nom/suite version arch [upgradable from: ancienne]
        match = re.match(r'^([^/\s]+)/(\S+)\s+(\S+)\s', line)
        if match:
            name, suite, candidate = match.groups()
            upgradable[name] = {
                'candidate': candidate,
                'security': 'security' in suite,
            }

    raw = run(['dpkg-query', '-W', '-f=${Package}\t${Version}\t${Section}\n'], timeout=60) or ''
    rows = []
    for line in raw.strip().splitlines():
        parts = line.split('\t')
        if len(parts) < 2:
            continue
        name, version = parts[0], parts[1]
        pending = upgradable.get(name)
        rows.append({
            'name': name,
            'version': version,
            'available': pending['candidate'] if pending else version,
            'license': dpkg_license(name),
            'section': parts[2] if len(parts) > 2 else None,
            'security': bool(pending and pending['security']),
            'outdated': pending is not None,
        })

    return sorted(rows, key=lambda r: r['name'])


def composer_packages():
    """
    Dependances PHP.

    Les versions disponibles viennent de `composer outdated`, execute dans le
    conteneur : c'est le seul endroit ou le vendor/ reellement utilise existe.
    Son champ latest-status distingue deja une montee compatible d'un
    changement de version majeure — inutile de le rededuire.
    """
    lock = {}
    try:
        data = json.loads((ROOT / 'composer.lock').read_text())
    except (OSError, json.JSONDecodeError):
        data = {}

    for section, is_dev in (('packages', False), ('packages-dev', True)):
        for pkg in data.get(section, []):
            lock[pkg['name']] = {
                'version': pkg.get('version'),
                'license': ', '.join(pkg.get('license', [])) or None,
                'dev': is_dev,
                'description': pkg.get('description'),
            }

    outdated = run_json([
        'sudo', '-n', 'docker', 'exec', 'ev-app',
        'composer', 'outdated', '--format=json', '--all', '--no-interaction',
    ], timeout=120) or {}

    status = {p['name']: p for p in outdated.get('installed', [])}

    rows = []
    for name, meta in lock.items():
        live = status.get(name, {})
        rows.append({
            'name': name,
            'version': (live.get('version') or meta['version'] or '').lstrip('v'),
            'available': (live.get('latest') or meta['version'] or '').lstrip('v'),
            'license': meta['license'],
            'dev': meta['dev'],
            'direct': bool(live.get('direct-dependency')),
            'abandoned': bool(live.get('abandoned')),
            # up-to-date | semver-safe-update | update-possible
            'status': live.get('latest-status') or ('not-installed' if meta['dev'] else 'unknown'),
            'description': meta['description'],
        })

    return sorted(rows, key=lambda r: r['name'])


def npm_packages():
    """
    Dependances JavaScript.

    Les versions et les licences sortent du fichier de verrouillage (format 3,
    qui porte la licence de presque chaque entree) ; les versions disponibles
    de `npm outdated`, qui ne couvre que les dependances declarees — les
    autres suivent leur parent, on ne les presente donc pas comme a mettre a
    jour soi-meme.
    """
    try:
        lock = json.loads((ROOT / 'package-lock.json').read_text())
    except (OSError, json.JSONDecodeError):
        return []

    try:
        manifest = json.loads((ROOT / 'package.json').read_text())
    except (OSError, json.JSONDecodeError):
        manifest = {}

    direct = set(manifest.get('dependencies', {})) | set(manifest.get('devDependencies', {}))
    dev_only = set(manifest.get('devDependencies', {}))

    outdated = run_json([
        'sudo', '-n', 'docker', 'run', '--rm',
        '-v', f'{ROOT}:/app', '-w', '/app', 'node:22-alpine',
        'npm', 'outdated', '--json', '--long',
    ], timeout=180, ok_codes=(0, 1)) or {}

    rows = []
    for path, meta in lock.get('packages', {}).items():
        if not path.startswith('node_modules/'):
            continue
        name = path[len('node_modules/'):]
        live = outdated.get(name, {})
        version = meta.get('version')
        rows.append({
            'name': name,
            'version': version,
            'available': live.get('latest') or version,
            'wanted': live.get('wanted') or version,
            'license': meta.get('license'),
            'dev': bool(meta.get('dev')) or name in dev_only,
            'direct': name in direct,
        })

    return sorted(rows, key=lambda r: r['name'])


def runtimes():
    """Versions des briques qui font tourner le site."""
    rows = []

    php = run(['sudo', '-n', 'docker', 'exec', 'ev-app', 'php', '-r', 'echo PHP_VERSION;'], timeout=30)
    if php:
        rows.append({'name': 'PHP', 'version': php.strip()})

    laravel = run([
        'sudo', '-n', 'docker', 'exec', 'ev-app', 'php', 'artisan', '--version',
    ], timeout=30)
    if laravel:
        rows.append({'name': 'Laravel', 'version': laravel.strip().replace('Laravel Framework ', '')})

    db = run([
        'sudo', '-n', 'docker', 'exec', 'ev-mariadb', 'mariadbd', '--version',
    ], timeout=30)
    if db:
        match = re.search(r'(\d+\.\d+\.\d+[-\w]*)', db)
        rows.append({'name': 'MariaDB', 'version': match.group(1) if match else db.strip()})

    web = run(
        ['sudo', '-n', 'docker', 'exec', 'ev-nginx', 'nginx', '-v'],
        timeout=30, stderr=True,
    )
    if web:
        match = re.search(r'nginx/(\S+)', web)
        if match:
            rows.append({'name': 'nginx', 'version': match.group(1)})

    node = run([
        'sudo', '-n', 'docker', 'run', '--rm', 'node:22-alpine', 'node', '--version',
    ], timeout=60)
    if node:
        rows.append({'name': 'Node (build)', 'version': node.strip().lstrip('v')})

    return rows


def main():
    if '--if-requested' in sys.argv:
        if not REQUEST_FLAG.exists():
            return 0
        REQUEST_FLAG.unlink(missing_ok=True)

    inventory = {
        'generated_at': datetime.now(timezone.utc).isoformat(timespec='seconds'),
        'host': host(),
        'runtimes': runtimes(),
        'containers': containers(),
        'apt': apt_packages(),
        'composer': composer_packages(),
        'npm': npm_packages(),
    }

    OUTPUT.parent.mkdir(parents=True, exist_ok=True)
    tmp = OUTPUT.with_suffix('.tmp')
    tmp.write_text(json.dumps(inventory, ensure_ascii=False, indent=1))
    # Remplacement atomique : la page ne doit jamais lire un fichier a moitie ecrit.
    tmp.replace(OUTPUT)
    os.chmod(OUTPUT, 0o644)

    print(f"{OUTPUT} : {len(inventory['apt'])} paquets systeme, "
          f"{len(inventory['composer'])} composer, {len(inventory['npm'])} npm")

    return 0


if __name__ == '__main__':
    sys.exit(main())
