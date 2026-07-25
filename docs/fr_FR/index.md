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
| **Durée d'impulsion (ms)** | Temps entre le ON et le OFF. Typiquement 500 à 800 ms. Défaut : 600. **Relais en auto-off/momentané : mettre 0** (le OFF n'est pas envoyé, évite une double impulsion). |
| **Temps de course complet (s)** | Temps porte fermée → ouverte. Sert au calcul de l'état estimé. Défaut : 18. |
| **Fermeture auto après (s)** | Si la centrale Somfy referme seule après un délai, l'indiquer ici **en secondes** (5-120, = réglage Somfy) pour que l'état estimé suive. `0` = désactivé. Le plugin n'envoie aucune impulsion, il reflète juste la fermeture. |
| **Inverser ouvert/fermé** | Inverse la sémantique ouvert/fermé partout (widget, texte, HomeKit). À cocher si l'état s'affiche à l'envers. |

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

La commande **État** utilise un widget maison embarqué (`garageDoor`) : un **volet roulant en SVG** qui s'anime selon le pourcentage — le tablier **s'enroule vers le haut** et une **barre (le coffre) reste au-dessus du cadre**. Libellé d'état + pourcentage. Aucune dépendance, rien à importer — versionné avec le plugin, lié automatiquement à la commande.

Le réglage **Style d'enroulement** propose **Barre fixe (A)** ou **Enroulement qui grossit (B)** (le coffre s'épaissit à l'ouverture).

Paramètres optionnels du widget (onglet *Affichage* de la commande) : `hauteur`, `largeur` (taille), `travel` (temps de course, réglé automatiquement), `rollgrow` (`1` = coffre qui grossit), `hidePercent` = `display:none` (masquer le pourcentage).

## Apple Home (Homebridge)

Le plugin est prêt pour **homebridge-jeedom**. Deux modes au choix (config de l'équipement, champ **Mode HomeKit**) :

- **Porte de garage** (défaut) : accessoire *GarageDoorOpener*. Commande info **État HomeKit** (`GARAGE_STATE`) + **Ouvrir** (`GB_OPEN`) / **Fermer** (`GB_CLOSE`) pour transmettre l'intention à Siri. Le plugin gère le moteur séquentiel : **1 impulsion pour ouvrir depuis fermé, 2 pour fermer depuis l'ouverture** (la 1re est absorbée par le moteur). Réglage *Délai entre 2 impulsions*. Valeurs d'état (defaults homebridge-jeedom) : ouvert=255, ouverture=254, arrêté=253, fermeture=252, **fermé=0**.
- **Volet — position (expérimental)** : accessoire *WindowCovering* avec curseur 0-100 %. ⚠️ Avec un **moteur à bouton unique sans retour de position**, le plugin ne peut pas garantir le sens de déplacement : le **positionnement au curseur se désynchronise** (l'estimation compte à l'envers si le moteur part dans l'autre sens). À réserver à un câblage **2 contacts** (OUT1 = ouvre, OUT2 = ferme) ou à un montage avec **capteur de position**. Pour un bouton unique, préférez le mode Porte de garage.

Après changement de mode : sauvegarder l'équipement puis **redémarrer le démon Homebridge** (le type d'accessoire change).

Côté Homebridge (plugin homebridge-jeedom) : inclure l'équipement dans les objets remontés, puis dans Apple Home la tuile « porte de garage » ouvre/ferme via une impulsion et reflète l'état estimé (ouvert / fermé / en mouvement).

## Fins de course (optionnel)

Pour **sécuriser l'état réel** et supprimer la dérive de l'estimation, on peut référencer une commande **info binaire** (contact ILS/reed via Z-Wave JS) active quand la porte est **fermée** et/ou **ouverte** (config *Utiliser des fins de course*). Le plugin recale l'état dès le déclenchement (réaction temps réel via un *listener*, plus une relecture périodique au cron en filet de sécurité). L'état sécurisé remonte partout, **y compris Apple Home**. Bonus : avec les contacts, le sens de déplacement devient déterministe.

## Limites

Sans fin de course, avec un **bouton unique séquentiel** (Ouvre → Stop → Ferme → Stop), il est physiquement impossible de garantir une direction : seule **Impulsion** est certaine, et Ouvrir / Fermer / Stop se basent sur l'état estimé (temps de course), qui peut se désynchroniser (obstacle, télécommande hors Jeedom). Câbler un contact de fin de course lève cette incertitude.
