# Changelog

Toutes les évolutions notables de ce plugin sont consignées ici.
Format inspiré de [Keep a Changelog](https://keepachangelog.com/fr/), versionnement [SemVer](https://semver.org/lang/fr/).

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

[0.3.1]: https://github.com/ebuyfr/jeedom-plugin-pilotevoletgarage/releases/tag/v0.3.1
[0.3.0]: https://github.com/ebuyfr/jeedom-plugin-pilotevoletgarage/releases/tag/v0.3.0
[0.2.0]: https://github.com/ebuyfr/jeedom-plugin-pilotevoletgarage/releases/tag/v0.2.0
[0.1.0]: https://github.com/ebuyfr/jeedom-plugin-pilotevoletgarage/releases/tag/v0.1.0
