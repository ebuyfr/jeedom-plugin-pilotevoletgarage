# Plugin Pilote Volet Garage

Pilotage d'une porte / volet de garage **Somfy** via un module **Fibaro FGBS-222 (Smart Implant)** exposé par le plugin **Z-Wave JS**.

## Principe

Le plugin ne communique **ni en Z-Wave ni en MQTT directement** : il délègue l'action physique à une commande Z-Wave JS existante (le relais **OUT1** du FGBS-222 câblé sur le bouton du moteur Somfy).

Comme aucune fin de course n'est câblée, l'état de la porte est **estimé** à partir du temps de course et de la dernière commande envoyée.

## Prérequis

- Le FGBS-222 est déjà appairé et visible dans le plugin **Z-Wave JS**.
- Le relais **OUT1** (endpoint 5) est câblé sur l'entrée bouton du moteur Somfy.
- Recommandé : régler le relais OUT1 du FGBS-222 en mode **momentané / auto-off** (paramètre Z-Wave) pour un vrai contact fugitif.

## Configuration d'une porte

| Champ | Description |
|-------|-------------|
| **Commande relais ON** | Commande action qui **ferme** le relais (ex. FGBS-222 : « OuvertureFermeture », `37-5-targetValue-true`). |
| **Commande relais OFF** | Commande action qui **ouvre** le relais (ex. « STOP », `37-5-targetValue-false`). Laisser vide si le relais est en mode auto-off. |
| **Durée d'impulsion (ms)** | Temps entre le ON et le OFF. Typiquement 500 à 800 ms. Défaut : 600. |
| **Temps de course complet (s)** | Temps porte fermée → ouverte. Sert au calcul de l'état estimé. Défaut : 18. |

Renseignez soit l'**id numérique** de la commande, soit sa **chaîne humaine** `#[objet][équipement][commande]#` (via le bouton de sélection).

## Commandes créées

| Commande | Type | Rôle |
|----------|------|------|
| **État** | info numérique (0-100) | Position estimée (0 = fermé, 100 = ouvert). Widget volet. |
| **État (texte)** | info texte | Libellé : Fermé / Ouvert / Ouverture… / Fermeture… / Arrêté. |
| **Ouvrir** | action | Meilleur effort : impulsion si la porte n'est pas déjà ouverte / en ouverture. |
| **Fermer** | action | Symétrique d'Ouvrir. |
| **Stop** | action | Impulsion uniquement si la porte est estimée en mouvement. |
| **Impulsion** | action | **Contrôle brut déterministe** : une impulsion sur le relais (logique séquentielle du bouton unique). |

## Widget

La commande **État** utilise un widget maison embarqué (`garageDoor`) : une **porte de garage sectionnelle en SVG** qui s'anime selon le pourcentage (fermée à 0 %, ouverte à 100 %), avec le libellé d'état et le pourcentage. Aucun fichier icône externe, aucune dépendance, rien à importer — le widget est versionné avec le plugin et lié automatiquement à la commande.

Paramètres optionnels du widget (onglet *Affichage* de la commande) : `hauteur`, `largeur` (taille de l'illustration), `hidePercent` = `display:none` (masquer le pourcentage).

## Apple Home (Homebridge)

Le plugin est prêt pour **homebridge-jeedom** : il crée une commande info **État HomeKit** (`GARAGE_STATE`) et la commande **Impulsion** est en `GB_TOGGLE`. Apple Home affiche alors un accessoire **porte de garage** (ouvrir / fermer + état).

Côté Homebridge (plugin homebridge-jeedom) : inclure l'équipement dans les objets remontés, puis dans Apple Home la tuile « porte de garage » ouvre/ferme via une impulsion et reflète l'état estimé (ouvert / fermé / en mouvement).

## Limites

Avec un **bouton unique séquentiel** (Ouvre → Stop → Ferme → Stop), il est physiquement impossible de garantir une direction. Seule **Impulsion** est certaine. Ouvrir / Fermer / Stop se basent sur l'état estimé, qui peut se désynchroniser (ex. obstacle, commande télécommande hors Jeedom). Un capteur de fin de course (ILS/reed sur une entrée du FGBS-222) supprimerait cette incertitude — évolution possible.
