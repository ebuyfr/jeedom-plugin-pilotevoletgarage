<?php
/* Petit script lancé en tâche détachée par le plugin après le démarrage d'un
 * mouvement. Après la temporisation (sleep côté shell), il recalcule l'état de
 * l'équipement et pousse l'état final (fermé/ouvert) sans attendre le cron.
 * Argument : id de l'eqLogic.
 */
require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';

if (!isset($argv[1]) || !ctype_digit((string) $argv[1])) {
    return;
}
$eqLogic = eqLogic::byId((int) $argv[1]);
if (is_object($eqLogic) && method_exists($eqLogic, 'tickEstimation')) {
    try {
        $eqLogic->tickEstimation();
    } catch (Exception $e) {
        log::add('pilotevoletgarage', 'error', 'settle: ' . $e->getMessage());
    }
}
