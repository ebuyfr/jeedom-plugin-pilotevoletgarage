<?php
if (!isConnect('admin')) {
    throw new Exception('{{401 - Accès non autorisé}}');
}
?>
<form class="form-horizontal">
    <fieldset>
        <div class="alert alert-info">
            {{Ce plugin ne nécessite aucune configuration globale. Ajoutez une porte de garage depuis l'onglet principal, puis renseignez les commandes du relais Z-Wave JS.}}
        </div>
    </fieldset>
</form>
