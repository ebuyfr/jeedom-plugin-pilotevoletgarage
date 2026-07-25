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
        $etat = $this->getCmd(null, 'etat');
        $hk   = $this->getCmd(null, 'etat_hk');
        $needSync = !is_object($etat) || !is_object($hk);
        if (is_object($etat)) {
            $t = $etat->getTemplate('dashboard');
            if ($t === '' || $t === 'core::garageDoor') {
                $needSync = true;
            }
        }
        $imp = $this->getCmd(null, 'impulsion');
        if (is_object($imp) && $imp->getGeneric_type() !== 'GB_TOGGLE') {
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
        // --- Info : état estimé 0-100 (support du widget porte de garage) ---
        $etat = $this->getCmd(null, 'etat');
        if (!is_object($etat)) {
            $etat = new pilotevoletgarageCmd();
            $etat->setLogicalId('etat');
            $etat->setEqLogic_id($this->getId());
            $etat->setName(__('État', __FILE__));
        }
        $etat->setType('info');
        $etat->setSubType('numeric');
        // Pas de type générique FLAP_* : sinon homebridge-jeedom exposerait
        // aussi un volet roulant en doublon dans Apple Home.
        $etat->setGeneric_type('');
        $etat->setUnite('%');
        $etat->setIsVisible(1);
        $etat->setIsHistorized(0);
        $etat->setConfiguration('minValue', 0);
        $etat->setConfiguration('maxValue', 100);
        // Widget maison embarqué. Le namespace DOIT être l'id du plugin, sinon
        // Jeedom préfixe par 'core::' et cherche le widget dans le cœur.
        $etat->setTemplate('dashboard', 'pilotevoletgarage::garageDoor');
        $etat->setTemplate('mobile', 'pilotevoletgarage::garageDoor');
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
        $hk->setGeneric_type('GARAGE_STATE');
        $hk->setIsVisible(0);
        $hk->setIsHistorized(0);
        $hk->save();

        // --- Actions --------------------------------------------------
        // Modèle bouton unique : Impulsion = GB_TOGGLE (un appui cycle
        // ouvre/stop/ferme), fidèle au fonctionnement réel du moteur Somfy.
        $this->createAction('ouvrir', __('Ouvrir', __FILE__), '', $etatId, 100);
        $this->createAction('fermer', __('Fermer', __FILE__), '', $etatId, 0);
        $this->createAction('stop', __('Stop', __FILE__), '', $etatId, null);
        $this->createAction('impulsion', __('Impulsion', __FILE__), 'GB_TOGGLE', $etatId, null);

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
    private function pulse() {
        $on = $this->resolveCmd('cmd_pulse_on');
        if (!is_object($on)) {
            throw new Exception(__('Commande relais ON non configurée sur ', __FILE__) . $this->getHumanName());
        }
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

    /* Démarre un mouvement estimé dans la direction voulue. */
    private function startMove($dir) {
        $this->setCache('pos', $this->currentPos());
        $this->setCache('dir', $dir);
        $this->setCache('start_ts', microtime(true));
        $this->setCache('moving', 1);
    }

    /* Arrête le mouvement estimé et fige la position. */
    private function stopMove() {
        $pos = $this->currentPos();
        $this->setCache('pos', $pos);
        $this->setCache('moving', 0);
        // Mémorise la dernière direction parcourue (utile au moteur séquentiel)
        $this->setCache('last_dir', $this->getCache('dir', self::DIR_UP));
    }

    /*
     * Appelée chaque minute par le cron : si le volet a fini sa course,
     * on l'arrête proprement et on cale la position sur 0 ou 100.
     */
    public function tickEstimation() {
        if ($this->getCache('moving', 0)) {
            $pos = $this->currentPos();
            $dir = $this->getCache('dir', self::DIR_UP);
            if (($dir === self::DIR_UP && $pos >= 100) || ($dir === self::DIR_DOWN && $pos <= 0)) {
                $this->setCache('pos', $dir === self::DIR_UP ? 100 : 0);
                $this->setCache('moving', 0);
                $this->setCache('last_dir', $dir);
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
        $etat = $this->getCmd(null, 'etat');
        if (is_object($etat)) {
            $etat->event($o);
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
        $this->pulse();
        if ($this->getCache('moving', 0)) {
            $this->stopMove();
        } else {
            $pos  = $this->currentPos();
            $last = $this->getCache('last_dir', self::DIR_DOWN);
            $dir  = ($last === self::DIR_UP) ? self::DIR_DOWN : self::DIR_UP;
            if ($pos >= 100) $dir = self::DIR_DOWN;
            if ($pos <= 0)   $dir = self::DIR_UP;
            $this->startMove($dir);
        }
        $this->refreshEtat();
    }

    /* Ouvrir (meilleur effort) : ne fait rien si déjà ouvert / en
     * ouverture ; sinon envoie une impulsion et estime une ouverture.
     * Par sécurité on ne ré-inverse jamais une porte en train de fermer :
     * on l'arrête d'abord. */
    public function actionOuvrir() {
        $dirOpen = $this->dirToOpen();
        if ($this->getCache('moving', 0)) {
            if ($this->getCache('dir', self::DIR_UP) === $dirOpen) {
                return; // déjà en ouverture
            }
            $this->pulse();      // en fermeture → on stoppe (sécurité)
            $this->stopMove();
            $this->refreshEtat();
            return;
        }
        if ($this->openness() >= 100) {
            return; // déjà ouvert
        }
        $this->pulse();
        $this->startMove($dirOpen);
        $this->refreshEtat();
    }

    /* Fermer (meilleur effort), symétrique de actionOuvrir(). */
    public function actionFermer() {
        $dirClose = $this->dirToClose();
        if ($this->getCache('moving', 0)) {
            if ($this->getCache('dir', self::DIR_UP) === $dirClose) {
                return; // déjà en fermeture
            }
            $this->pulse();      // en ouverture → on stoppe (sécurité)
            $this->stopMove();
            $this->refreshEtat();
            return;
        }
        if ($this->openness() <= 0) {
            return; // déjà fermé
        }
        $this->pulse();
        $this->startMove($dirClose);
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
            case 'rafraichir':
                $eqLogic->refreshEtat();
                break;
        }
    }
}
