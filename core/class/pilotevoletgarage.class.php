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

    /* *********************** Méthodes statiques *********************** */

    /*
     * Tâche cron exécutée chaque minute : fait "avancer" puis "arrêter"
     * l'estimation de position pour chaque volet en mouvement.
     */
    public static function cron() {
        foreach (self::byType('pilotevoletgarage', true) as $eqLogic) {
            /** @var pilotevoletgarage $eqLogic */
            try {
                $eqLogic->tickEstimation();
            } catch (Exception $e) {
                log::add('pilotevoletgarage', 'error', 'cron: ' . $e->getMessage());
            }
        }
    }

    /* *********************** Méthodes d'instance ********************** */

    /*
     * (Ré)création idempotente des commandes du plugin après chaque
     * sauvegarde de l'équipement.
     */
    public function postSave() {
        // --- Info : état estimé (0 = fermé, 100 = ouvert) --------------
        $etat = $this->getCmd(null, 'etat');
        if (!is_object($etat)) {
            $etat = new pilotevoletgarageCmd();
            $etat->setLogicalId('etat');
            $etat->setEqLogic_id($this->getId());
            $etat->setName(__('État', __FILE__));
        }
        $etat->setType('info');
        $etat->setSubType('numeric');
        $etat->setGeneric_type('FLAP_STATE');
        $etat->setUnite('%');
        $etat->setIsVisible(1);
        $etat->setIsHistorized(0);
        $etat->setConfiguration('minValue', 0);
        $etat->setConfiguration('maxValue', 100);
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

        // --- Action : Ouvrir ------------------------------------------
        $this->createAction('ouvrir', __('Ouvrir', __FILE__), 'FLAP_UP', $etatId, 100);
        // --- Action : Fermer ------------------------------------------
        $this->createAction('fermer', __('Fermer', __FILE__), 'FLAP_DOWN', $etatId, 0);
        // --- Action : Stop --------------------------------------------
        $this->createAction('stop', __('Stop', __FILE__), 'FLAP_STOP', $etatId, null);
        // --- Action : Impulsion (contrôle brut, toujours fiable) ------
        $this->createAction('impulsion', __('Impulsion', __FILE__), '', $etatId, null);
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
        if ($genericType !== '') {
            $cmd->setGeneric_type($genericType);
        }
        $cmd->setIsVisible(1);
        if ($etatId !== null) {
            $cmd->setValue($etatId); // lie le bouton au widget volet
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

    /* Position courante estimée (0..100), calculée en temps réel. */
    public function currentPos() {
        $pos = (float) $this->getCache('pos', 0);
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
        if (!$this->getCache('moving', 0)) {
            return;
        }
        $pos = $this->currentPos();
        $dir = $this->getCache('dir', self::DIR_UP);
        if (($dir === self::DIR_UP && $pos >= 100) || ($dir === self::DIR_DOWN && $pos <= 0)) {
            $this->setCache('pos', $dir === self::DIR_UP ? 100 : 0);
            $this->setCache('moving', 0);
            $this->setCache('last_dir', $dir);
        }
        $this->refreshEtat();
    }

    /* Pousse l'état estimé vers les commandes info (widget). */
    public function refreshEtat() {
        $pos  = (int) round($this->currentPos());
        $etat = $this->getCmd(null, 'etat');
        if (is_object($etat)) {
            $etat->event($pos);
        }
        $txt = $this->getCmd(null, 'etat_texte');
        if (is_object($txt)) {
            $txt->event($this->stateLabel($pos));
        }
    }

    private function stateLabel($pos) {
        if ($this->getCache('moving', 0)) {
            return $this->getCache('dir', self::DIR_UP) === self::DIR_UP
                ? __('Ouverture…', __FILE__)
                : __('Fermeture…', __FILE__);
        }
        if ($pos <= 0)   return __('Fermé', __FILE__);
        if ($pos >= 100) return __('Ouvert', __FILE__);
        return __('Arrêté', __FILE__) . ' (' . $pos . '%)';
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
        if ($this->getCache('moving', 0)) {
            if ($this->getCache('dir', self::DIR_UP) === self::DIR_UP) {
                return; // déjà en ouverture
            }
            $this->pulse();      // en fermeture → on stoppe (sécurité)
            $this->stopMove();
            $this->refreshEtat();
            return;
        }
        if ($this->currentPos() >= 100) {
            return; // déjà ouvert
        }
        $this->pulse();
        $this->startMove(self::DIR_UP);
        $this->refreshEtat();
    }

    /* Fermer (meilleur effort), symétrique de actionOuvrir(). */
    public function actionFermer() {
        if ($this->getCache('moving', 0)) {
            if ($this->getCache('dir', self::DIR_UP) === self::DIR_DOWN) {
                return; // déjà en fermeture
            }
            $this->pulse();      // en ouverture → on stoppe (sécurité)
            $this->stopMove();
            $this->refreshEtat();
            return;
        }
        if ($this->currentPos() <= 0) {
            return; // déjà fermé
        }
        $this->pulse();
        $this->startMove(self::DIR_DOWN);
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
