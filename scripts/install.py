#!/usr/bin/env python3
"""
Premiere installation d'EV Recharges sur une machine neuve.

Seuls Python 3 (stdlib) et Docker avec le plugin Compose v2 sont requis : PHP,
Composer et Node tournent dans des conteneurs jetables. Rejouable sans risque
(ne regenere ni le .env, ni les mots de passe existants).

    python3 scripts/install.py                  # interactif
    python3 scripts/install.py --yes --admin-email moi@exemple.fr
    python3 scripts/install.py --help
"""

import argparse
import base64
import getpass
import os
import re
import secrets
import shutil
import subprocess
import sys
import time
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
ENV = ROOT / '.env'
ENV_EXAMPLE = ROOT / '.env.example'


def say(msg):
    print(f'\n==> {msg}', flush=True)


def die(msg):
    print(f'\nErreur : {msg}', file=sys.stderr)
    sys.exit(1)


def run(cmd, check=True, capture=False, input_text=None):
    """Lance une commande depuis la racine du depot."""
    result = subprocess.run(
        cmd, cwd=ROOT, text=True, input=input_text,
        stdout=subprocess.PIPE if capture else None,
        stderr=subprocess.STDOUT if capture else None,
    )
    if check and result.returncode != 0:
        die(f"commande en echec : {' '.join(cmd)}" + (f"\n{result.stdout}" if capture else ''))
    return result


def compose(*args, **kw):
    return run(['docker', 'compose', *args], **kw)


def ask(question, default='', secret=False):
    suffix = f' [{default}]' if default else ''
    prompt = f'{question}{suffix} : '
    value = (getpass.getpass(prompt) if secret else input(prompt)).strip()
    return value or default


def confirm(question, default=False):
    answer = input(f"{question} [{'O/n' if default else 'o/N'}] : ").strip().lower()
    return default if not answer else answer in ('o', 'oui', 'y', 'yes')


# --- .env -------------------------------------------------------------------

def read_env():
    values = {}
    if ENV.exists():
        for line in ENV.read_text().splitlines():
            m = re.match(r'^([A-Z0-9_]+)=(.*)$', line)
            if m:
                values[m.group(1)] = m.group(2)
    return values


def set_env(key, value):
    text = ENV.read_text()
    line = f'{key}={value}'
    if re.search(rf'^{key}=.*$', text, flags=re.M):
        text = re.sub(rf'^{key}=.*$', lambda _: line, text, flags=re.M)
    else:
        text += ('' if text.endswith('\n') else '\n') + line + '\n'
    ENV.write_text(text)


def set_env_if_empty(key, value):
    if not read_env().get(key):
        set_env(key, value)


# --- etapes -----------------------------------------------------------------

def check_prerequisites():
    say('Verification des prerequis')
    if shutil.which('docker') is None:
        die("Docker n'est pas installe (https://docs.docker.com/engine/install/).")
    if run(['docker', 'compose', 'version'], check=False, capture=True).returncode != 0:
        die("le plugin « docker compose » (v2) est introuvable. "
            "Installer docker-compose-plugin ; l'ancien docker-compose 1.x n'est pas supporte.")
    if run(['docker', 'info'], check=False, capture=True).returncode != 0:
        die("le demon Docker ne repond pas : est-il demarre, et votre utilisateur est-il "
            "dans le groupe « docker » (ou lancez le script avec sudo) ?")
    print('Docker et Docker Compose : OK')


def configure_env(args):
    say('Configuration (.env)')
    if not ENV.exists():
        shutil.copy(ENV_EXAMPLE, ENV)
        print('.env cree depuis .env.example')

    set_env_if_empty('APP_KEY', 'base64:' + base64.b64encode(secrets.token_bytes(32)).decode())
    set_env_if_empty('DB_PASSWORD', secrets.token_hex(16))
    set_env_if_empty('DB_ROOT_PASSWORD', secrets.token_hex(16))
    set_env_if_empty('MQTT_PASSWORD', secrets.token_hex(16))
    print('Cle applicative et mots de passe de base generes (ils restent dans .env).')

    env = read_env()
    current_url = env.get('APP_URL', '')
    if args.app_url:
        set_env('APP_URL', args.app_url)
    elif not args.yes and (not current_url or current_url == 'http://localhost:8096'):
        port = env.get('HTTP_PORT') or '8098'
        set_env('APP_URL', ask('URL par laquelle vous atteindrez l’application', f'http://localhost:{port}'))

    if args.public:
        set_env('HTTP_BIND', '0.0.0.0')
        print('Port web publie sur toutes les interfaces (HTTP_BIND=0.0.0.0).')


def proxy_env():
    """Variables de proxy de l'hote, transmises aux conteneurs jetables."""
    args = []
    for name in ('HTTP_PROXY', 'HTTPS_PROXY', 'NO_PROXY', 'http_proxy', 'https_proxy', 'no_proxy'):
        if os.environ.get(name):
            args += ['-e', f'{name}={os.environ[name]}']
    return args


def build_dependencies(args):
    say('Dependances PHP (Composer, conteneur jetable)')
    if (ROOT / 'vendor' / 'autoload.php').exists() and not args.rebuild:
        print('vendor/ deja present (utiliser --rebuild pour refaire).')
    else:
        run(['docker', 'run', '--rm', *proxy_env(), '-v', f'{ROOT}:/app', '-w', '/app', 'composer:2',
             'install', '--no-dev', '--optimize-autoloader', '--no-interaction', '--ignore-platform-reqs'])

    say('Assets front (Node, conteneur jetable)')
    if (ROOT / 'public' / 'build' / 'manifest.json').exists() and not args.rebuild:
        print('public/build deja present (utiliser --rebuild pour refaire).')
        return
    node = ['docker', 'run', '--rm', *proxy_env(), '-v', f'{ROOT}:/app', '-w', '/app', 'node:22-alpine']
    for attempt in range(1, 4):
        # npm sort parfois en 0 sans avoir tout installe (coupure reseau) :
        # on verifie le resultat plutot que le code de retour.
        run([*node, 'npm', 'install', '--no-audit', '--no-fund'], check=False)
        if (ROOT / 'node_modules' / '.bin' / 'vite').exists():
            break
        print(f'  npm install incomplet, nouvel essai ({attempt}/3)...', flush=True)
        # Un node_modules a moitie ecrit fait echouer l'essai suivant (ENOTEMPTY).
        shutil.rmtree(ROOT / 'node_modules', ignore_errors=True)
    else:
        die("npm install n'a pas abouti (reseau ?). Relancer le script : il reprend ou il s'est arrete.")
    run([*node, 'npm', 'run', 'build'])
    if not (ROOT / 'public' / 'build' / 'manifest.json').exists():
        die("la construction des assets n'a pas produit public/build/manifest.json.")


def prepare_storage():
    say('Dossiers inscriptibles')
    for sub in ('app/system', 'framework/cache/data', 'framework/sessions', 'framework/views', 'logs'):
        (ROOT / 'storage' / sub).mkdir(parents=True, exist_ok=True)
    # php-fpm tourne sous un autre uid que l'utilisateur de l'hote.
    run(['chmod', '-R', 'a+rwX', 'storage'])


def start_containers(with_mqtt):
    say("Demarrage des conteneurs (la premiere construction d'image prend quelques minutes)")
    args = ['--profile', 'mqtt'] if with_mqtt else []
    compose(*args, 'up', '-d', '--build')


def wait_and_migrate():
    say('Migration de la base (attente de MariaDB)')
    for attempt in range(1, 31):
        result = compose('exec', '-T', 'app', 'php', 'artisan', 'migrate', '--force',
                         check=False, capture=True)
        if result.returncode == 0:
            print(result.stdout.strip().splitlines()[-1] if result.stdout.strip() else 'OK')
            return
        print(f'  MariaDB pas encore prete ({attempt}/30)...', flush=True)
        time.sleep(4)
    die('la migration echoue toujours apres 2 minutes. Voir : docker compose logs mariadb app')


def create_admin(args):
    say('Premier administrateur')
    email = args.admin_email
    if not email:
        if args.yes:
            print('Aucun --admin-email : compte a creer plus tard avec « php artisan user:create ».')
            return
        email = ask('Email de l’administrateur')
        if not email:
            return
    password = args.admin_password or os.environ.get('EV_ADMIN_PASSWORD')
    if not password:
        if args.yes:
            password = secrets.token_urlsafe(16)
            print(f'Mot de passe genere (a changer dans « Mon compte ») : {password}')
        else:
            while True:
                password = ask('Mot de passe (12 caracteres minimum)', secret=True)
                if len(password) >= 12 and password == ask('Confirmer', secret=True):
                    break
                print('Trop court (12 minimum) ou different, recommencer.')
    result = compose('exec', '-T', 'app', 'php', 'artisan', 'user:create', email, '--admin',
                     f'--password={password}', check=False, capture=True)
    print(result.stdout.strip())
    if result.returncode != 0 and 'existe déjà' not in result.stdout:
        die("creation de l'administrateur impossible.")


def setup_mqtt():
    say('Broker MQTT (Mosquitto)')
    env = read_env()
    user, password = env.get('MQTT_USER') or 'ev', env['MQTT_PASSWORD']
    if not env.get('MQTT_USER'):
        set_env('MQTT_USER', user)
    passwd = ROOT / 'docker' / 'mosquitto' / 'passwd'
    run(['docker', 'run', '--rm', '-v', f'{ROOT / "docker" / "mosquitto"}:/mosquitto/config',
         'eclipse-mosquitto:2', 'mosquitto_passwd', '-b', '-c', '/mosquitto/config/passwd', user, password])
    # Le conteneur ecrit en root : lisible par l'uid de mosquitto.
    run(['chmod', 'a+r', str(passwd)], check=False)
    print(f'Compte MQTT « {user} » cree ; le mot de passe est dans .env (MQTT_PASSWORD).')


def install_cron(args):
    line = f'* * * * * cd {ROOT} && docker compose exec -T app php artisan schedule:run >/dev/null 2>&1'
    if args.install_cron:
        if os.geteuid() != 0:
            print('--install-cron demande les droits root ; ajoutez la ligne ci-dessous a votre crontab :')
        else:
            Path('/etc/cron.d/ev-recharges').write_text(f'{line}\n'.replace('* * * * * ', '* * * * * root ', 1))
            print('Tache planifiee installee : /etc/cron.d/ev-recharges')
            return
    say('Planificateur (a faire une fois)')
    print('L’application a besoin que cette ligne tourne chaque minute (crontab -e) :\n')
    print(f'    {line}\n')


def import_data(args):
    if args.import_data is None:
        if args.yes:
            return
        args.import_data = confirm(
            'Importer maintenant la base des bornes (≈ 150 Mo, 3-4 min) et les prix carburants ?')
    if not args.import_data:
        return
    say('Import des bornes de recharge (IRVE)')
    compose('exec', '-T', 'app', 'php', 'artisan', 'irve:import', check=False)
    say('Import de l’historique des prix carburants')
    compose('exec', '-T', 'app', 'php', 'artisan', 'fuel-prices:backfill', check=False)


def main():
    ap = argparse.ArgumentParser(description="Premiere installation d'EV Recharges (Docker).")
    ap.add_argument('--yes', '-y', action='store_true', help='non interactif : valeurs par defaut')
    ap.add_argument('--app-url', help="URL publique (APP_URL), ex. https://ev.exemple.fr")
    ap.add_argument('--admin-email', help="email du premier administrateur")
    ap.add_argument('--admin-password', help="son mot de passe (sinon demande, ou EV_ADMIN_PASSWORD)")
    ap.add_argument('--public', action='store_true',
                    help="publier le port web sur toutes les interfaces (sinon 127.0.0.1 seulement)")
    ap.add_argument('--with-mqtt', action='store_true', help="installer aussi le broker MQTT (telemetrie OBD)")
    ap.add_argument('--import-data', dest='import_data', action='store_true', default=None,
                    help="importer les bornes IRVE et les prix carburants")
    ap.add_argument('--rebuild', action='store_true', help="refaire vendor/ et les assets meme s'ils existent")
    ap.add_argument('--install-cron', action='store_true', help="installer la tache planifiee (root)")
    args = ap.parse_args()

    if not (ROOT / 'docker-compose.yml').exists():
        die('docker-compose.yml introuvable : lancer le script depuis une copie du depot.')

    if not args.yes and not args.with_mqtt:
        args.with_mqtt = confirm('Installer le broker MQTT pour la telemetrie du vehicule (optionnel) ?')

    check_prerequisites()
    configure_env(args)
    build_dependencies(args)
    prepare_storage()
    if args.with_mqtt:
        setup_mqtt()
    start_containers(args.with_mqtt)
    wait_and_migrate()
    create_admin(args)
    import_data(args)
    install_cron(args)

    env = read_env()
    bind = env.get('HTTP_BIND') or '127.0.0.1'
    port = env.get('HTTP_PORT') or '8098'
    say('Termine')
    print(f"Application servie sur http://{'<ip-de-la-machine>' if bind == '0.0.0.0' else bind}:{port}/login")
    print('Le trafic est en HTTP : pour un acces depuis Internet, placez un reverse proxy TLS devant.')


if __name__ == '__main__':
    try:
        main()
    except KeyboardInterrupt:
        die('interrompu.')
