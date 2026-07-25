# Plugin Jeedom — Pilote Volet Garage

![version](https://img.shields.io/badge/version-0.10.0-blue) ![jeedom](https://img.shields.io/badge/jeedom-%E2%89%A5%204.2-brightgreen)

Plugin Jeedom pour piloter une porte / volet de garage **Somfy** via un module **Fibaro FGBS-222 (Smart Implant)** exposé par le plugin **Z-Wave JS**.

- **Transport** : délégation aux commandes Z-Wave JS existantes via `execCmd()` — pas de démon, pas de MQTT direct.
- **Contrôle** : impulsion unique séquentielle sur le relais OUT1 (bouton du moteur Somfy).
- **État** : estimé (temps de course + dernière commande), faute de fin de course câblée.

## Installation (Market OTA / Git)

Racine du dépôt = racine du plugin. Dans Jeedom : ajouter ce dépôt comme source Market GitHub, puis installer le plugin `pilotevoletgarage`.

## Documentation

Voir [`docs/fr_FR/index.md`](docs/fr_FR/index.md).

## Compatibilité

Développé et testé sur Jeedom 4.6.1 (Raspberry Pi).

## Licence

GPL-3.0
