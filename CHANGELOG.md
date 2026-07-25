# Changelog

Toutes les évolutions notables de ce plugin sont consignées ici.
Format inspiré de [Keep a Changelog](https://keepachangelog.com/fr/), versionnement [SemVer](https://semver.org/lang/fr/).

## [0.7.0] - 2026-07-25
### Corrigé
- **Décompte du mouvement saccadé/en retard** : le widget restait figé sur le % de départ puis sautait à 0/100 (parfois ~10 s après la porte réelle), faute de mise à jour temps réel entre deux passages du cron (1 min). Le widget **anime désormais la porte côté navigateur** à la vitesse du temps de course : ouverture/fermeture fluides et arrêt à l'heure. Pendant un mouvement, le serveur pousse la cible (0/100) et transmet le temps de course au widget (paramètre `travel`, réglé automatiquement).

## [0.6.0] - 2026-07-25
### Corrigé
- **État HomeKit inversé/incorrect** : les valeurs envoyées ne correspondaient pas à ce qu'attend homebridge-jeedom. Utilisation des valeurs par défaut réelles d'une porte de garage : ouvert=255, ouverture=254, arrêté=253, fermeture=252, **fermé=0** (au lieu de l'énum HomeKit 0-4). Apple Home affiche désormais le bon état sans configuration supplémentaire.
### Modifié
- **Retour au modèle bouton unique** : `Impulsion` = `GB_TOGGLE` (un appui cycle ouvre/stop/ferme), fidèle au moteur Somfy. Ouvrir/Fermer n'ont plus `GB_OPEN`/`GB_CLOSE`. Remplace l'approche de la v0.5.0.

## [0.5.0] - 2026-07-24
### Ajouté
- Option **Inverser ouvert/fermé** : inverse la sémantique (widget, état texte, état HomeKit) si l'état s'affiche à l'envers par rapport à la réalité.
### Modifié
- **Apple Home** : Ouvrir → `GB_OPEN` et Fermer → `GB_CLOSE` (au lieu d'un unique `GB_TOGGLE` sur Impulsion). Apple Home indique désormais explicitement le sens voulu, ce qui fiabilise l'estimation et **corrige l'icône qui se refermait toute seule** avec un moteur à bouton unique.
- Impulsion n'a plus de type générique HomeKit (reste le contrôle brut du dashboard).
### Documentation
- Relais du FGBS-222 en **auto-off/momentané** : mettre la durée d'impulsion à **0** pour ne pas envoyer le OFF (évite une double impulsion).

## [0.4.1] - 2026-07-24
### Corrigé
- L'état HomeKit est désormais rafraîchi à chaque cycle de cron et à l'initialisation, même au repos (sinon Apple Home affichait « Arrêté » au lieu de « Fermé » tant qu'aucun mouvement n'avait eu lieu).

## [0.4.0] - 2026-07-24
### Ajouté
- **Apple Home / Homebridge** : nouvelle commande info `État HomeKit` (type générique `GARAGE_STATE`, valeurs 0=ouvert / 1=fermé / 2=ouverture / 3=fermeture / 4=arrêté) et commande `Impulsion` passée en `GB_TOGGLE`. homebridge-jeedom expose ainsi un accessoire **porte de garage** (GarageDoorOpener) dans Apple Home.
- Auto-réparation étendue (`ensureConfig`) : le cron crée la commande HomeKit et applique les types génériques sur les équipements existants, sans re-sauvegarde manuelle.
### Modifié
- Retrait des types génériques `FLAP_*` sur État/Ouvrir/Fermer/Stop (évite un volet roulant en doublon dans Apple Home). Les boutons restent inchangés côté dashboard Jeedom.

## [0.3.2] - 2026-07-24
### Ajouté
- Auto-réparation du widget : le cron applique le template `garageDoor` sur la commande État s'il manque (équipement créé avant le widget) ou s'il était resté sur l'ancienne valeur buguée `core::garageDoor`. Plus besoin de re-sauvegarder l'équipement à la main.

## [0.3.1] - 2026-07-24
### Corrigé
- Le widget **garageDoor** n'était pas appliqué : `setTemplate` recevait `garageDoor` que Jeedom préfixait en `core::garageDoor` (widget cherché dans le cœur au lieu du plugin). Corrigé en `pilotevoletgarage::garageDoor`.

## [0.3.0] - 2026-07-24
### Ajouté
- Widget maison **garageDoor** : porte de garage sectionnelle en SVG, animée de 0 % (fermé) à 100 % (ouvert), en versions dashboard et mobile. Aucune dépendance d'assets externes.
- Liaison automatique du widget à la commande **État** (via `setTemplate`).
- Icône du plugin (`plugin_info/pilotevoletgarage_icon.png`), grisée automatiquement par Jeedom quand le plugin est désactivé.

## [0.2.0] - 2026-07-24
### Ajouté
- Persistance de la position : le pourcentage est figé au **Stop** et le décompte reprend sur cette valeur à la fermeture.
### Corrigé
- Re-lecture de la position depuis la commande `État` si le cache est purgé (redémarrage, ménage interne).
### Sécurité
- Ignore des fichiers de secrets/tokens dans `.gitignore`.

## [0.1.0] - 2026-07-24
### Ajouté
- Version initiale. Pilotage d'une porte de garage Somfy via un module Fibaro FGBS-222 exposé par Z-Wave JS.
- Délégation aux commandes Z-Wave JS existantes (`execCmd`), impulsion unique séquentielle, état estimé par temps de course.
- Commandes : État, État (texte), Ouvrir, Fermer, Stop, Impulsion, Rafraîchir.

[0.7.0]: https://github.com/ebuyfr/jeedom-plugin-pilotevoletgarage/releases/tag/v0.7.0
[0.6.0]: https://github.com/ebuyfr/jeedom-plugin-pilotevoletgarage/releases/tag/v0.6.0
[0.5.0]: https://github.com/ebuyfr/jeedom-plugin-pilotevoletgarage/releases/tag/v0.5.0
[0.4.1]: https://github.com/ebuyfr/jeedom-plugin-pilotevoletgarage/releases/tag/v0.4.1
[0.4.0]: https://github.com/ebuyfr/jeedom-plugin-pilotevoletgarage/releases/tag/v0.4.0
[0.3.2]: https://github.com/ebuyfr/jeedom-plugin-pilotevoletgarage/releases/tag/v0.3.2
[0.3.1]: https://github.com/ebuyfr/jeedom-plugin-pilotevoletgarage/releases/tag/v0.3.1
[0.3.0]: https://github.com/ebuyfr/jeedom-plugin-pilotevoletgarage/releases/tag/v0.3.0
[0.2.0]: https://github.com/ebuyfr/jeedom-plugin-pilotevoletgarage/releases/tag/v0.2.0
[0.1.0]: https://github.com/ebuyfr/jeedom-plugin-pilotevoletgarage/releases/tag/v0.1.0
