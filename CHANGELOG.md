# Changelog

Toutes les évolutions notables de ce plugin sont consignées ici.
Format inspiré de [Keep a Changelog](https://keepachangelog.com/fr/), versionnement [SemVer](https://semver.org/lang/fr/).

## [0.12.0] - 2026-07-25
### Ajouté
- **Contacts de fin de course** (optionnels, activables) : référencer une commande info binaire pour la position **fermée** et/ou **ouverte** (ILS/reed via Z-Wave JS). Le plugin **recale l'état réel** dès le déclenchement (supprime la dérive d'estimation), et l'état sécurisé remonte partout — y compris Apple Home (`etat_hk`).
  - **Temps réel** via un *listener* + **filet périodique** (relecture au cron, ex. au démarrage).
  - **Bonus** : avec les contacts, le sens devient déterministe (un contact « fermé » qui se relâche ⇒ ouverture certaine ; « ouvert » qui se relâche ⇒ fermeture).
  - Nettoyage des listeners à la suppression de l'équipement.

## [0.11.0] - 2026-07-25
### Ajouté
- **Affichages de temps sur le widget** (activables/désactivables) :
  - **Durée dans l'état** : temps écoulé (h:m:s, live) depuis que la porte est dans son état courant (ouvert / fermé / arrêté).
  - **Compte à rebours fermeture auto** : temps restant avant la fermeture automatique Somfy (si un délai est configuré).
  - Alimentés par une commande interne `meta` (horodatages) ; rendu et décompte **côté navigateur** (mise à jour chaque seconde).
### Note
- La configuration de l'équipement (réglages) est stockée en base Jeedom : elle est **conservée lors des mises à jour du plugin** (le git pull ne remplace que le code). L'état estimé (cache) survit aussi aux redémarrages.

## [0.10.1] - 2026-07-25
### Corrigé / Modifié
- **Fermeture auto en secondes** : le réglage passe de minutes à **secondes** (`auto_close_sec`, 0-120) pour coller au Somfy (5 s → 2 min). Déclenchement rendu **précis** via une tâche détachée programmée à l'échéance (le cron reste en filet de sécurité) au lieu d'attendre le prochain passage du cron.

## [0.10.0] - 2026-07-25
### Modifié
- **Widget en volet roulant** : le tablier ne monte plus au-dessus du linteau pour disparaître ; il **s'enroule vers le haut** (le bord bas remonte) et une **barre (le coffre) reste au-dessus du cadre**. Rendu plus fidèle à un volet.
### Ajouté
- Réglage **Style d'enroulement** : **Barre fixe (A)** ou **Enroulement qui grossit (B)** (le coffre s'épaissit à mesure de l'ouverture). Transmis au widget via le paramètre `rollgrow`.

## [0.9.2] - 2026-07-25
### Corrigé
- **Chrono d'estimation plus précis** : l'origine du chrono est désormais l'instant de l'**appui** (impulsion ON) au lieu d'être posée après le OFF (~600 ms plus tard), ce qui décalait légèrement la position estimée (pleine ouverture affichée un peu en-dessous de 100 %). `pulse()`/`doublePulse()` renvoient l'instant réel du départ (2e impulsion pour un double appui). Pensez à caler **Temps de course** sur la durée réelle mesurée.

## [0.9.1] - 2026-07-25
### Ajouté
- Réglage **Fermeture auto après (min)** : si la centrale Somfy referme la porte d'elle-même après un délai, le plugin **reflète cette fermeture dans l'état estimé** (le cron détecte la porte restée pleinement ouverte depuis N min et rejoue une fermeture **sans envoyer d'impulsion** — le moteur agit seul). Apple Home repasse alors en « fermeture » puis « fermé ». `0` = désactivé. Toute commande réarme le minuteur.

## [0.9.0] - 2026-07-25
### Modifié / Corrigé
- **Fermeture depuis l'ouverture complète (Apple Home)** : le moteur séquentiel demande **2 impulsions** pour repartir en fermeture (la 1re est absorbée). Pour que Siri/Apple Home transmette l'intention (ouvrir ≠ fermer), Ouvrir passe en `GB_OPEN` et Fermer en `GB_CLOSE` (Impulsion n'a plus de générique). Le plugin envoie alors le bon nombre d'impulsions : **1 pour ouvrir depuis fermé, 2 pour fermer depuis l'ouverture ou en cours d'ouverture** (et 2 pour inverser un mouvement).
- Nouveau réglage **Délai entre 2 impulsions (ms)** (défaut 1200) pour laisser le moteur enregistrer 2 appuis distincts.
### Note
- Après mise à jour : sauvegarder l'équipement puis **redémarrer le démon Homebridge** (les types génériques changent).

## [0.8.2] - 2026-07-25
### Corrigé
- Erreurs JS (`TypeError ... parentNode ... null`) dans l'aperçu de l'onglet Commandes : l'appel `jeedom.cmd.refreshValue` du widget est désormais protégé (n'agit que si le widget est présent dans le DOM, avec garde try/catch). Sans effet sur le dashboard.

## [0.8.1] - 2026-07-25
### Modifié
- Mode **Volet** marqué **expérimental** : avec un moteur à bouton unique sans retour de position, le sens ne peut pas être garanti et le positionnement au curseur se désynchronise. Le mode **Porte de garage** reste recommandé. Documentation et libellés clarifiés (câblage 2 contacts ou capteur requis pour une vraie position).

## [0.8.0] - 2026-07-25
### Ajouté
- **Choix du mode HomeKit** (config équipement) : **Porte de garage** (ouvert/fermé, défaut) ou **Volet** (WindowCovering, position exacte 0-100 %).
- Mode volet : commande **Position** (curseur `FLAP_SLIDER`) + `FLAP_STATE`/`FLAP_UP`/`FLAP_DOWN`/`FLAP_STOP`. Le plugin rejoint au mieux la position visée (démarrage moteur puis impulsion de stop à l'arrivée, via l'estimation et le settle détaché). Aux extrémités (0/100), arrêt automatique du moteur.
### Note
- Après changement de mode : sauvegarder l'équipement puis **redémarrer le démon Homebridge** (le type d'accessoire change). La position intermédiaire dépend du bon réglage du temps de course et du fonctionnement de la tâche détachée (sinon la porte peut dépasser la cible).

## [0.7.1] - 2026-07-25
### Corrigé
- **Apple Home : état « fermé »/« ouvert » en retard de 10-15 s** en fin de course. Un mouvement programme désormais une tâche détachée qui pousse l'état final à l'échéance du temps de course, sans attendre le cron (1 min). Le cron reste en filet de sécurité (dégradation gracieuse si `exec` indisponible).

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

[0.12.0]: https://github.com/ebuyfr/jeedom-plugin-pilotevoletgarage/releases/tag/v0.12.0
[0.11.0]: https://github.com/ebuyfr/jeedom-plugin-pilotevoletgarage/releases/tag/v0.11.0
[0.10.1]: https://github.com/ebuyfr/jeedom-plugin-pilotevoletgarage/releases/tag/v0.10.1
[0.10.0]: https://github.com/ebuyfr/jeedom-plugin-pilotevoletgarage/releases/tag/v0.10.0
[0.9.2]: https://github.com/ebuyfr/jeedom-plugin-pilotevoletgarage/releases/tag/v0.9.2
[0.9.1]: https://github.com/ebuyfr/jeedom-plugin-pilotevoletgarage/releases/tag/v0.9.1
[0.9.0]: https://github.com/ebuyfr/jeedom-plugin-pilotevoletgarage/releases/tag/v0.9.0
[0.8.2]: https://github.com/ebuyfr/jeedom-plugin-pilotevoletgarage/releases/tag/v0.8.2
[0.8.1]: https://github.com/ebuyfr/jeedom-plugin-pilotevoletgarage/releases/tag/v0.8.1
[0.8.0]: https://github.com/ebuyfr/jeedom-plugin-pilotevoletgarage/releases/tag/v0.8.0
[0.7.1]: https://github.com/ebuyfr/jeedom-plugin-pilotevoletgarage/releases/tag/v0.7.1
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
