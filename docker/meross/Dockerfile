FROM python:3.11-slim

RUN pip install --no-cache-dir meross_iot flask

COPY docker/meross/app.py /app/app.py
WORKDIR /app

# Pas de port publie sur l'hote : atteint uniquement depuis ev-app, via le
# reseau Docker interne du projet (nom de service "meross"), meme principe
# que ev-app -> ev-mariadb.
EXPOSE 8000

CMD ["python", "app.py"]
