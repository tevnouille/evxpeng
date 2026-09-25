import asyncio
import os

from flask import Flask, jsonify, request
from meross_iot.http_api import MerossHttpClient
from meross_iot.manager import MerossManager

# Petit service HTTP interne (pas de port publie sur l'hote, atteint
# seulement depuis ev-app via le reseau Docker du projet) : PHP ne peut pas
# parler MQTT directement (contrainte deja connue de ce projet pour
# l'ingestion telemetrie), et ev-app n'a pas acces au socket Docker de
# l'hote pour lancer un conteneur a la demande. Une requete par action,
# reconnexion complete a chaque fois (~3-5 s) plutot qu'une session Meross
# gardee ouverte : plus simple, et l'action reste manuelle/peu frequente
# (bouton de portail, pas une boucle de telemetrie).
app = Flask(__name__)

API_BASE_URL = "https://iotx-eu.meross.com"


async def operer(uuid: str, action: str, channel: int) -> dict:
    email = os.environ["MEROSS_EMAIL"]
    password = os.environ["MEROSS_PASSWORD"]

    http_client = await MerossHttpClient.async_from_user_password(
        api_base_url=API_BASE_URL, email=email, password=password
    )
    manager = MerossManager(http_client=http_client)

    try:
        await manager.async_init()
        await manager.async_device_discovery()
        appareils = manager.find_devices(device_uuids=(uuid,))

        if not appareils:
            return {"ok": False, "error": "appareil introuvable"}

        appareil = appareils[0]
        await appareil.async_update()

        if action == "open":
            await appareil.async_open(channel=channel)
        elif action == "close":
            await appareil.async_close(channel=channel)
        elif action != "state":
            return {"ok": False, "error": f"action inconnue: {action}"}

        if action != "state":
            # Laisse le temps a l'etat de se propager (capteur de position
            # du portail/garage) avant de le relire, sinon on renvoie encore
            # l'ancien etat.
            await asyncio.sleep(2)
            await appareil.async_update()

        return {"ok": True, "open": appareil.get_is_open(channel=channel)}
    finally:
        manager.close()
        await http_client.async_logout()


@app.post("/control")
def control():
    donnees = request.get_json(force=True, silent=True) or {}
    uuid = donnees.get("uuid")
    action = donnees.get("action")
    channel = int(donnees.get("channel", 0))

    if not uuid or action not in ("open", "close", "state"):
        return jsonify({"ok": False, "error": "parametres invalides"}), 400

    try:
        resultat = asyncio.run(operer(uuid, action, channel))
    except Exception as e:
        return jsonify({"ok": False, "error": str(e)}), 502

    return jsonify(resultat), (200 if resultat.get("ok") else 502)


@app.get("/sante")
def sante():
    return jsonify({"ok": True})


if __name__ == "__main__":
    app.run(host="0.0.0.0", port=8000)
