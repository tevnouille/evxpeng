# TODO

- **Revoir le seuil de découpage des déplacements sur `/deplacements`.**
  `resources/js/trips.js` (`TRIP_GAP_SECONDS`) coupe la carte en plusieurs
  trajets dès qu'un écart de plus de 15 minutes sépare deux relevés
  consécutifs, sur l'hypothèse que le boîtier OBD ne remonte plus rien
  moteur coupé. Pas encore vérifié sur plusieurs journées réelles si ce
  seuil est le bon (trop court : un long feu/embouteillage coupe un trajet
  en deux ; trop long : deux trajets séparés par un arrêt court se
  retrouvent dans la même couleur).
