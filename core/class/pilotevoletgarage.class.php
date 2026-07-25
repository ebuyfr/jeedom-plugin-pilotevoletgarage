<?php
/* Plugin "Pilote Volet Garage" pour Jeedom
 * Pilotage d'une porte de garage Somfy via un module Fibaro FGBS-222
 * exposé par le plugin Z-Wave JS.
 *
 * Principe : le plugin ne parle NI Z-Wave NI MQTT directement. Il délègue
 * l'action physique à des commandes Z-Wave JS déjà existantes (le relais
 * OUT1 du FGBS-222) via execCmd(). L'état de la porte n'ayant pas de retour
 * (pas de fin de course câblé), il est ESTIMÉ à partir du temps de course
 * et de la dernière commande envoyée.
 */

/* Ne jamais protéger ce fichier par isConnect() : il est chargé aussi
 * hors contexte utilisateur (cron, API, démon). */
require_once __DIR__ . '/../../../../core/php/core.inc.php';

class pilotevoletgarage extends eqLogic {

    /* ************************ Constantes ****************************** */

    // Direction interne : 'up' = ouverture (position croît), 'down' = fermeture
    const DIR_UP   = 'up';
    const DIR_DOWN = 'down';

    // Valeurs d'état attendues par homebridge-jeedom (defaults customizedValues
    // d'une porte de garage : voir index.js ligne ~3592). PAS l'énum HomeKit 0-4.
    const HK_OPEN    = 255;
    const HK_OPENING = 254;
    const HK_STOPPED = 253;
    const HK_CLOSING = 252;
    const HK_CLOSED  = 0;

    /* *********************** Méthodes statiques *********************** */

    /*
     * Tâche cron exécutée chaque minute : fait "avancer" puis "arrêter"
     * l'estimation de position pour chaque volet en mouvement.
     */
    public static function cron() {
        foreach (self::byType('pilotevoletgarage', true) as $eqLogic) {
            /** @var pilotevoletgarage $eqLogic */
            try {
                $eqLogic->ensureConfig();
                $eqLogic->tickEstimation();
            } catch (Exception $e) {
                log::add('pilotevoletgarage', 'error', 'cron: ' . $e->getMessage());
            }
        }
    }

    /*
     * Auto-réparation (appelée par le cron) : ré-applique la configuration des
     * commandes si un équipement existant est en retard sur le code (widget
     * absent, commande HomeKit manquante, Impulsion pas en GB_TOGGLE...).
     * postSave ne se rejouant pas lors d'une mise à jour de plugin, ce filet
     * de sécurité évite d'avoir à re-sauvegarder l'équipement à la main.
     */
    public function ensureConfig() {
        $mode = ($this->getConfiguration('hk_mode', 'garage') === 'volet') ? 'volet' : 'garage';
        $etat = $this->getCmd(null, 'etat');
        $hk   = $this->getCmd(null, 'etat_hk');
        $needSync = !is_object($etat) || !is_object($hk) || !is_object($this->getCmd(null, 'position'));
        if (is_object($etat)) {
            $t = $etat->getTemplate('dashboard');
            if ($t === '' || $t === 'core::garageDoor') {
                $needSync = true;
            }
            // Générique de la commande état selon le mode (FLAP_STATE en volet).
            if ($etat->getGeneric_type() !== ($mode === 'volet' ? 'FLAP_STATE' : '')) {
                $needSync = true;
            }
        }
        $ouv = $this->getCmd(null, 'ouvrir');
        if (is_object($ouv) && $ouv->getGeneric_type() !== ($mode === 'volet' ? 'FLAP_UP' : 'GB_OPEN')) {
            $needSync = true;
        }
        if ($needSync) {
            $this->syncCommands();
            log::add('pilotevoletgarage', 'info', 'Configuration auto-réparée sur ' . $this->getHumanName());
        }
    }

    /* *********************** Méthodes d'instance ********************** */

    public function postSave() {
        $this->syncCommands();
    }

    /*
     * (Ré)création idempotente des commandes du plugin.
     * Appelée à la sauvegarde de l'équipement ET par le cron (auto-réparation).
     */
    public function syncCommands() {
        // Mode d'exposition Apple Home : 'garage' (porte, ouvert/fermé) ou
        // 'volet' (WindowCovering, position exacte 0-100 %).
        $mode = ($this->getConfiguration('hk_mode', 'garage') === 'volet') ? 'volet' : 'garage';

        // --- Info : état/position estimé 0-100 (widget + position HomeKit) ---
        $etat = $this->getCmd(null, 'etat');
        if (!is_object($etat)) {
            $etat = new pilotevoletgarageCmd();
            $etat->setLogicalId('etat');
            $etat->setEqLogic_id($this->getId());
            $etat->setName(__('État', __FILE__));
        }
        $etat->setType('info');
        $etat->setSubType('numeric');
        // Mode volet : FLAP_STATE = position courante du volet dans Apple Home.
        // Mode garage : pas de générique ici (l'état passe par etat_hk/GARAGE_STATE).
        $etat->setGeneric_type($mode === 'volet' ? 'FLAP_STATE' : '');
        $etat->setUnite('%');
        $etat->setIsVisible(1);
        $etat->setIsHistorized(0);
        $etat->setConfiguration('minValue', 0);
        $etat->setConfiguration('maxValue', 100);
        // Widget maison embarqué. Le namespace DOIT être l'id du plugin, sinon
        // Jeedom préfixe par 'core::' et cherche le widget dans le cœur.
        $etat->setTemplate('dashboard', 'pilotevoletgarage::garageDoor');
        $etat->setTemplate('mobile', 'pilotevoletgarage::garageDoor');
        // Transmet le temps de course au widget (animation client-side #travel#).
        $params = $etat->getDisplay('parameters');
        if (!is_array($params)) { $params = array(); }
        $params['travel'] = (int) $this->getConfiguration('travel_time', 18);
        // Style d'enroulement du volet : '1' = coffre qui grossit (B), sinon barre fixe (A).
        $params['rollgrow'] = ($this->getConfiguration('roll_grow', '0') === '1') ? '1' : '0';
        $etat->setDisplay('parameters', $params);
        $etat->save();
        $etatId = $etat->getId();

        // --- Info texte : libellé lisible de l'état --------------------
        $txt = $this->getCmd(null, 'etat_texte');
        if (!is_object($txt)) {
            $txt = new pilotevoletgarageCmd();
            $txt->setLogicalId('etat_texte');
            $txt->setEqLogic_id($this->getId());
            $txt->setName(__('État (texte)', __FILE__));
        }
        $txt->setType('info');
        $txt->setSubType('string');
        $txt->setIsVisible(1);
        $txt->setIsHistorized(0);
        $txt->save();

        // --- Info : état HomeKit (GARAGE_STATE, valeurs 0-4) -----------
        // 0=Ouvert 1=Fermé 2=Ouverture 3=Fermeture 4=Arrêté (enum CurrentDoorState).
        // Lu par homebridge-jeedom pour exposer un GarageDoorOpener dans Apple Home.
        $hk = $this->getCmd(null, 'etat_hk');
        if (!is_object($hk)) {
            $hk = new pilotevoletgarageCmd();
            $hk->setLogicalId('etat_hk');
            $hk->setEqLogic_id($this->getId());
            $hk->setName(__('État HomeKit', __FILE__));
        }
        $hk->setType('info');
        $hk->setSubType('numeric');
        // GARAGE_STATE seulement en mode garage (sinon pas d'accessoire garage).
        $hk->setGeneric_type($mode === 'garage' ? 'GARAGE_STATE' : '');
        $hk->setIsVisible(0);
        $hk->setIsHistorized(0);
        $hk->save();

        // --- Actions --------------------------------------------------
        // Mode garage : Ouvrir=GB_OPEN / Fermer=GB_CLOSE → Apple Home transmet
        // l'intention (ouvrir vs fermer), ce qui permet d'envoyer le bon nombre
        // d'impulsions (le moteur séquentiel en demande 2 pour fermer depuis
        // l'ouverture). Impulsion reste le contrôle brut du dashboard.
        // Mode volet : Ouvrir/Fermer/Stop = FLAP_UP/DOWN/STOP + curseur position.
        $this->createAction('ouvrir', __('Ouvrir', __FILE__), $mode === 'volet' ? 'FLAP_UP' : 'GB_OPEN', $etatId, 100);
        $this->createAction('fermer', __('Fermer', __FILE__), $mode === 'volet' ? 'FLAP_DOWN' : 'GB_CLOSE', $etatId, 0);
        $this->createAction('stop', __('Stop', __FILE__), $mode === 'volet' ? 'FLAP_STOP' : '', $etatId, null);
        $this->createAction('impulsion', __('Impulsion', __FILE__), '', $etatId, null);

        // --- Action : Position (curseur) — utile en mode volet ---------
        $pos = $this->getCmd(null, 'position');
        if (!is_object($pos)) {
            $pos = new pilotevoletgarageCmd();
            $pos->setLogicalId('position');
            $pos->setEqLogic_id($this->getId());
            $pos->setName(__('Position', __FILE__));
        }
        $pos->setType('action');
        $pos->setSubType('slider');
        $pos->setGeneric_type($mode === 'volet' ? 'FLAP_SLIDER' : '');
        $pos->setIsVisible($mode === 'volet' ? 1 : 0);
        $pos->setConfiguration('minValue', 0);
        $pos->setConfiguration('maxValue', 100);
        if ($etatId !== null) { $pos->setValue($etatId); }
        $pos->save();

        // --- Action : Rafraîchir --------------------------------------
        $refresh = $this->getCmd(null, 'rafraichir');
        if (!is_object($refresh)) {
            $refresh = new pilotevoletgarageCmd();
            $refresh->setLogicalId('rafraichir');
            $refresh->setEqLogic_id($this->getId());
            $refresh->setName(__('Rafraîchir', __FILE__));
        }
        $refresh->setType('action');
        $refresh->setSubType('other');
        $refresh->setIsVisible(0);
        $refresh->save();

        // Initialise la valeur des commandes info (dont l'état HomeKit).
        $this->refreshEtat();
    }

    /* Crée/maj une commande action liée à l'info d'état */
    private function createAction($logicalId, $name, $genericType, $etatId, $value) {
        $cmd = $this->getCmd(null, $logicalId);
        if (!is_object($cmd)) {
            $cmd = new pilotevoletgarageCmd();
            $cmd->setLogicalId($logicalId);
            $cmd->setEqLogic_id($this->getId());
            $cmd->setName($name);
        }
        $cmd->setType('action');
        $cmd->setSubType('other');
        $cmd->setGeneric_type($genericType); // '' = aucun (efface un FLAP_* hérité)
        $cmd->setIsVisible(1);
        if ($etatId !== null) {
            $cmd->setValue($etatId);
        }
        $cmd->save();
        return $cmd;
    }

    /* ------------------- Résolution des cmd déléguées ---------------- */

    /*
     * Retourne l'objet cmd Z-Wave JS référencé dans la configuration.
     * Accepte soit un id numérique, soit une chaîne humaine #[...]#.
     */
    private function resolveCmd($confKey) {
        $v = trim($this->getConfiguration($confKey, ''));
        if ($v === '') {
            return null;
        }
        if (ctype_digit($v)) {
            $c = cmd::byId($v);
            return is_object($c) ? $c : null;
        }
        $c = cmd::byString($v);
        return is_object($c) ? $c : null;
    }

    /* ------------------------- Impulsion physique -------------------- */

    /*
     * Envoie une impulsion sur le relais OUT1 : ON, petite tempo, OFF.
     * Si le relais est configuré en mode "auto-off/momentané" côté FGBS,
     * l'OFF est simplement redondant et sans effet.
     */
    /* Envoie une impulsion. Retourne l'instant de l'APPUI (ON), qui correspond
     * au début réel du mouvement — à utiliser comme origine du chrono d'estimation
     * (sinon le OFF envoyé après pulse_ms décalerait le départ de ~600 ms). */
    private function pulse() {
        $on = $this->resolveCmd('cmd_pulse_on');
        if (!is_object($on)) {
            throw new Exception(__('Commande relais ON non configurée sur ', __FILE__) . $this->getHumanName());
        }
        $t = microtime(true);
        $on->execCmd();
        $ms = (int) $this->getConfiguration('pulse_ms', 600);
        if ($ms > 0) {
            usleep($ms * 1000);
            $off = $this->resolveCmd('cmd_pulse_off');
            if (is_object($off)) {
                $off->execCmd();
            }
        }
        log::add('pilotevoletgarage', 'info', 'Impulsion envoyée : ' . $this->getHumanName());
        return $t;
    }

    /* ------------------------- Estimation d'état -------------------- */

    /*
     * Position de référence figée (0..100), PERSISTÉE.
     * Si le cache a été purgé (redémarrage du Pi, ménage interne de Jeedom),
     * on relit la dernière position durable depuis la commande info 'etat'
     * afin de ne jamais repartir bêtement de 0.
     */
    private function getBasePos() {
        $pos = $this->getCache('pos', null);
        if ($pos === null || $pos === '') {
            // La cmd 'etat' stocke le TAUX D'OUVERTURE (inversé si option active) :
            // on reconvertit vers la position interne.
            $etat = $this->getCmd(null, 'etat');
            $stored = is_object($etat) ? $etat->execCmd() : null;
            $open = is_numeric($stored) ? (float) $stored : 0.0;
            $pos = $this->getConfiguration('invert', 0) ? (100 - $open) : $open;
            $this->setCache('pos', $pos);
        }
        return (float) $pos;
    }

    /* Position courante estimée (0..100), calculée en temps réel. */
    public function currentPos() {
        $pos = $this->getBasePos();
        if ($this->getCache('moving', 0)) {
            $start  = (float) $this->getCache('start_ts', microtime(true));
            $travel = max(1, (int) $this->getConfiguration('travel_time', 18));
            $delta  = ((microtime(true) - $start) / $travel) * 100;
            if ($this->getCache('dir', self::DIR_UP) === self::DIR_UP) {
                $pos = min(100, $pos + $delta);
            } else {
                $pos = max(0, $pos - $delta);
            }
        }
        return $pos;
    }

    /* Démarre un mouvement estimé dans la direction voulue.
     * $target (taux d'ouverture 0-100) = position intermédiaire visée, ou '' pour
     * aller jusqu'à la butée. */
    private function startMove($dir, $target = '', $startTs = null) {
        $this->setCache('pos', $this->currentPos());
        $this->setCache('dir', $dir);
        // Origine du chrono = instant de l'appui si fourni (mouvement réel),
        // sinon maintenant (ex. fermeture auto Somfy sans impulsion).
        $this->setCache('start_ts', ($startTs === null) ? microtime(true) : $startTs);
        $this->setCache('moving', 1);
        $this->setCache('target_open', ($target === '' || $target === null) ? '' : (int) $target);
        // Toute action réarme le minuteur de fermeture automatique Somfy.
        $this->setCache('open_since', '');
        $this->scheduleSettle();
    }

    /* Convertit un taux d'ouverture (0-100) en position interne. */
    private function opennessToPos($o) {
        return $this->getConfiguration('invert', 0) ? (100 - $o) : $o;
    }

    /*
     * Programme, en tâche détachée, la mise à jour de l'état final quand le
     * mouvement aura atteint la butée — pour qu'Apple Home (qui lit l'état
     * serveur) affiche fermé/ouvert à l'heure, sans attendre le cron (1 min).
     * Dégradation gracieuse : si exec est indisponible, le cron s'en charge.
     */
    private function scheduleSettle() {
        if (!$this->getCache('moving', 0)) {
            return;
        }
        $travel = max(1, (int) $this->getConfiguration('travel_time', 18));
        $o = $this->openness();
        $opening = $this->opennessIncreasing();
        $target = $this->getCache('target_open', '');
        $goal = ($target === '' || $target === null) ? ($opening ? 100 : 0) : (int) $target;
        $remain = abs($goal - $o) / 100.0 * $travel;
        $sec = max(1, (int) ceil($remain) + 1); // petite marge
        $script = realpath(__DIR__ . '/../../resources/settle.php');
        if ($script === false || !function_exists('exec')) {
            return;
        }
        @exec('(sleep ' . $sec . '; php ' . escapeshellarg($script) . ' ' . (int) $this->getId() . ') >/dev/null 2>&1 &');
    }

    /* Programme, en tâche détachée, la vérification de fermeture automatique à
     * l'échéance du délai Somfy — pour un déclenchement précis, sans attendre le
     * cron (1 min). Le cron reste en filet de sécurité. */
    private function scheduleAutoClose($sec) {
        $sec = (int) $sec;
        $script = realpath(__DIR__ . '/../../resources/settle.php');
        if ($sec <= 0 || $script === false || !function_exists('exec')) {
            return;
        }
        @exec('(sleep ' . ($sec + 1) . '; php ' . escapeshellarg($script) . ' ' . (int) $this->getId() . ') >/dev/null 2>&1 &');
    }

    /* Arrête le mouvement estimé et fige la position. */
    private function stopMove() {
        $pos = $this->currentPos();
        $this->setCache('pos', $pos);
        $this->setCache('moving', 0);
        // Mémorise la dernière direction parcourue (utile au moteur séquentiel)
        $this->setCache('last_dir', $this->getCache('dir', self::DIR_UP));
        $this->setCache('target_open', '');
    }

    /*
     * Appelée chaque minute par le cron : si le volet a fini sa course,
     * on l'arrête proprement et on cale la position sur 0 ou 100.
     */
    public function tickEstimation() {
        if ($this->getCache('moving', 0)) {
            $o = $this->openness();
            $opening = $this->opennessIncreasing();
            $target = $this->getCache('target_open', '');
            $hasTarget = ($target !== '' && $target !== null);
            $goal = $hasTarget ? (int) $target : ($opening ? 100 : 0);
            $reached = $opening ? ($o >= $goal) : ($o <= $goal);
            if ($reached) {
                // Position intermédiaire visée : le moteur ne s'arrête pas seul,
                // il faut lui envoyer une impulsion de stop. Aux butées (0/100),
                // le moteur s'arrête tout seul.
                if ($hasTarget && $goal > 0 && $goal < 100) {
                    $this->pulse();
                }
                $this->setCache('pos', $this->opennessToPos($goal));
                $this->setCache('moving', 0);
                $this->setCache('last_dir', $this->getCache('dir', self::DIR_UP));
                $this->setCache('target_open', '');
            }
        }

        // Fermeture automatique Somfy : si la porte est restée pleinement ouverte
        // (et à l'arrêt) plus longtemps que le délai configuré, la centrale Somfy
        // la referme physiquement d'elle-même. On reflète alors l'estimation de
        // fermeture SANS envoyer d'impulsion (le moteur agit seul).
        if (!$this->getCache('moving', 0)) {
            if ($this->openness() >= 100) {
                $sec   = (int) $this->getConfiguration('auto_close_sec', 0);
                $since = $this->getCache('open_since', '');
                if ($since === '' || $since === null) {
                    $this->setCache('open_since', microtime(true));
                    if ($sec > 0) {
                        $this->scheduleAutoClose($sec); // déclenchement précis à l'échéance
                    }
                } elseif ($sec > 0 && (microtime(true) - (float) $since) >= $sec) {
                    $this->setCache('open_since', '');
                    $this->startMove($this->dirToClose()); // sans pulse : le Somfy referme seul
                    log::add('pilotevoletgarage', 'info', 'Fermeture automatique Somfy reflétée sur ' . $this->getHumanName());
                }
            } else {
                $this->setCache('open_since', '');
            }
        }

        // Toujours rafraîchir : garde l'état HomeKit alimenté même au repos.
        $this->refreshEtat();
    }

    /* ------------------- Couche d'affichage (avec inversion) --------- */

    /* Taux d'OUVERTURE affiché (0 = fermé, 100 = ouvert), après inversion
     * éventuelle. La position interne (currentPos) reste inchangée ; seule
     * la sémantique ouvert/fermé est inversée par l'option 'invert'. */
    private function openness() {
        $pos = $this->currentPos();
        return $this->getConfiguration('invert', 0) ? (100 - $pos) : $pos;
    }

    /* Vrai si le taux d'ouverture augmente (= la porte s'ouvre). */
    private function opennessIncreasing() {
        $posUp = ($this->getCache('dir', self::DIR_UP) === self::DIR_UP); // pos interne croît
        return $this->getConfiguration('invert', 0) ? !$posUp : $posUp;
    }

    /* Direction interne (pos) pour aller vers l'ouverture / la fermeture. */
    private function dirToOpen()  { return $this->getConfiguration('invert', 0) ? self::DIR_DOWN : self::DIR_UP; }
    private function dirToClose() { return $this->getConfiguration('invert', 0) ? self::DIR_UP : self::DIR_DOWN; }

    /* Pousse l'état estimé vers les commandes info (widget + HomeKit). */
    public function refreshEtat() {
        $o = (int) round($this->openness());
        // Mode garage : pendant un mouvement on pousse la CIBLE (0/100) pour que
        // le widget anime lui-même à la vitesse du temps de course (fluide, sans
        // dépendre du cron). Mode volet : on pousse la position RÉELLE, car c'est
        // la source de CurrentPosition pour Apple Home.
        $mode = ($this->getConfiguration('hk_mode', 'garage') === 'volet') ? 'volet' : 'garage';
        $display = $o;
        if ($mode === 'garage' && $this->getCache('moving', 0)) {
            $display = $this->opennessIncreasing() ? 100 : 0;
        }
        $etat = $this->getCmd(null, 'etat');
        if (is_object($etat)) {
            $etat->event($display);
        }
        $txt = $this->getCmd(null, 'etat_texte');
        if (is_object($txt)) {
            $txt->event($this->stateLabel($o));
        }
        $hk = $this->getCmd(null, 'etat_hk');
        if (is_object($hk)) {
            $hk->event($this->hkState());
        }
    }

    /*
     * État aux valeurs attendues par homebridge-jeedom (customizedValues par
     * défaut d'une porte de garage) : ouvert=255, ouverture=254, arrêté=253,
     * fermeture=252, fermé=0.
     */
    private function hkState() {
        if ($this->getCache('moving', 0)) {
            return $this->opennessIncreasing() ? self::HK_OPENING : self::HK_CLOSING;
        }
        $o = $this->openness();
        if ($o <= 0)   return self::HK_CLOSED;
        if ($o >= 100) return self::HK_OPEN;
        return self::HK_STOPPED;
    }

    private function stateLabel($o) {
        if ($this->getCache('moving', 0)) {
            return $this->opennessIncreasing()
                ? __('Ouverture…', __FILE__)
                : __('Fermeture…', __FILE__);
        }
        if ($o <= 0)   return __('Fermé', __FILE__);
        if ($o >= 100) return __('Ouvert', __FILE__);
        return __('Arrêté', __FILE__) . ' (' . $o . '%)';
    }

    /* ------------------------- Actions publiques -------------------- */

    /* Impulsion brute : contrôle physique déterministe. Fait aussi
     * avancer le modèle séquentiel (arrête si en mouvement, sinon repart
     * dans le sens opposé au dernier mouvement — logique du bouton unique). */
    public function actionImpulsion() {
        $t = $this->pulse();
        if ($this->getCache('moving', 0)) {
            $this->stopMove();
        } else {
            $pos  = $this->currentPos();
            $last = $this->getCache('last_dir', self::DIR_DOWN);
            $dir  = ($last === self::DIR_UP) ? self::DIR_DOWN : self::DIR_UP;
            if ($pos >= 100) $dir = self::DIR_DOWN;
            if ($pos <= 0)   $dir = self::DIR_UP;
            $this->startMove($dir, '', $t);
        }
        $this->refreshEtat();
    }

    /* Ouvrir (meilleur effort) : ne fait rien si déjà ouvert / en
     * ouverture ; sinon envoie une impulsion et estime une ouverture.
     * Par sécurité on ne ré-inverse jamais une porte en train de fermer :
     * on l'arrête d'abord. */
    /* Deux impulsions espacées : nécessaire au moteur séquentiel pour repartir
     * dans l'autre sens (la 1re est absorbée = stop, la 2e lance le mouvement). */
    private function doublePulse() {
        $this->pulse();
        $gap = (int) $this->getConfiguration('double_gap_ms', 1200);
        if ($gap > 0) {
            usleep($gap * 1000);
        }
        return $this->pulse(); // le mouvement réel démarre à la 2e impulsion
    }

    /* Ouvrir : 1 impulsion depuis fermé/arrêt, 2 pour inverser une fermeture. */
    public function actionOuvrir() {
        $moving  = $this->getCache('moving', 0);
        $opening = $moving && $this->opennessIncreasing();
        $closing = $moving && !$this->opennessIncreasing();
        $o = $this->openness();
        if ($opening) {
            return; // déjà en ouverture
        }
        if (!$moving && $o >= 100) {
            return; // déjà ouvert
        }
        if ($closing) {
            $t = $this->doublePulse(); // inverser une fermeture : stop + ouvre
        } else {
            $t = $this->pulse();       // depuis fermé/arrêt : une impulsion ouvre
        }
        $this->startMove($this->dirToOpen(), '', $t);
        $this->refreshEtat();
    }

    /* Fermer : 2 impulsions si la porte est ouverte ou en ouverture (le moteur
     * séquentiel en a besoin pour repartir en fermeture), 1 depuis un arrêt
     * intermédiaire. */
    public function actionFermer() {
        $moving  = $this->getCache('moving', 0);
        $opening = $moving && $this->opennessIncreasing();
        $closing = $moving && !$this->opennessIncreasing();
        $o = $this->openness();
        if ($closing) {
            return; // déjà en fermeture
        }
        if (!$moving && $o <= 0) {
            return; // déjà fermé
        }
        if ($opening || (!$moving && $o >= 100)) {
            $t = $this->doublePulse(); // en ouverture ou butée ouverte : 2 impulsions
        } else {
            $t = $this->pulse();       // arrêt intermédiaire : 1 impulsion
        }
        $this->startMove($this->dirToClose(), '', $t);
        $this->refreshEtat();
    }

    /* Stop : impulsion uniquement si le volet est estimé en mouvement. */
    public function actionStop() {
        if ($this->getCache('moving', 0)) {
            $this->pulse();
            $this->stopMove();
        }
        $this->refreshEtat();
    }

    /* Position (curseur, mode volet) : rejoint au mieux le taux d'ouverture visé.
     * Pour une position intermédiaire, démarre le moteur puis programme une
     * impulsion de stop à l'arrivée (via le settle détaché / cron). */
    public function actionPosition($target) {
        $target = (int) round($target);
        if ($target < 0) { $target = 0; }
        if ($target > 100) { $target = 100; }
        $cur = $this->openness();
        if (abs($target - $cur) < 2) {
            return; // déjà à la position visée
        }
        if ($target <= 0)   { $this->actionFermer(); return; } // butée basse (arrêt auto)
        if ($target >= 100) { $this->actionOuvrir(); return; } // butée haute (arrêt auto)

        // Position intermédiaire.
        if ($this->getCache('moving', 0)) {
            $this->pulse();          // stoppe le mouvement en cours
            $this->stopMove();
            $cur = $this->openness();
        }
        $dir = ($target > $cur) ? $this->dirToOpen() : $this->dirToClose();
        $t = $this->pulse();
        $this->startMove($dir, $target, $t);
        $this->refreshEtat();
    }
}

class pilotevoletgarageCmd extends cmd {

    /* Empêche la suppression manuelle des commandes du plugin. */
    public function dontRemoveCmd() {
        return true;
    }

    public function execute($_options = array()) {
        /** @var pilotevoletgarage $eqLogic */
        $eqLogic = $this->getEqLogic();
        switch ($this->getLogicalId()) {
            case 'ouvrir':
                $eqLogic->actionOuvrir();
                break;
            case 'fermer':
                $eqLogic->actionFermer();
                break;
            case 'stop':
                $eqLogic->actionStop();
                break;
            case 'impulsion':
                $eqLogic->actionImpulsion();
                break;
            case 'position':
                $eqLogic->actionPosition(isset($_options['slider']) ? $_options['slider'] : 0);
                break;
            case 'rafraichir':
                $eqLogic->refreshEtat();
                break;
        }
    }
}
