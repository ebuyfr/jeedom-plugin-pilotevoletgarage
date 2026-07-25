<?php
if (!isConnect('admin')) {
    throw new Exception('{{401 - Accès non autorisé}}');
}
$plugin = plugin::byId('pilotevoletgarage');
sendVarToJS('eqType', $plugin->getId());
$eqLogics = eqLogic::byType($plugin->getId());
?>
<div class="row row-overflow">
    <div class="col-xs-12 eqLogicThumbnailDisplay">
        <legend><i class="fas fa-cog"></i> {{Gestion}}</legend>
        <div class="eqLogicThumbnailContainer">
            <div class="cursor eqLogicAction logoPrimary" data-action="add">
                <i class="fas fa-plus-circle"></i>
                <br>
                <span>{{Ajouter}}</span>
            </div>
            <div class="cursor eqLogicAction logoSecondary" data-action="gotoPluginConf">
                <i class="fas fa-wrench"></i>
                <br>
                <span>{{Configuration}}</span>
            </div>
        </div>
        <legend><i class="fas fa-warehouse"></i> {{Mes portes de garage}}</legend>
        <div class="eqLogicThumbnailContainer">
            <?php
            foreach ($eqLogics as $eqLogic) {
                $opacity = ($eqLogic->getIsEnable()) ? '' : 'disableCard';
                echo '<div class="eqLogicDisplayCard cursor ' . $opacity . '" data-eqLogic_id="' . $eqLogic->getId() . '">';
                echo '<img src="' . $eqLogic->getImage() . '"/>';
                echo '<br>';
                echo '<span class="name">' . $eqLogic->getHumanName(true, true) . '</span>';
                echo '</div>';
            }
            ?>
        </div>
    </div>

    <div class="col-xs-12 eqLogic" style="display: none;">
        <div class="input-group pull-right" style="display:inline-flex">
            <span class="input-group-btn">
                <a class="btn btn-default eqLogicAction roundedLeft" data-action="configure"><i class="fas fa-cogs"></i><span class="hidden-xs"> {{Configuration avancée}}</span></a>
                <a class="btn btn-default eqLogicAction" data-action="copy"><i class="fas fa-copy"></i><span class="hidden-xs"> {{Dupliquer}}</span></a>
                <a class="btn btn-success eqLogicAction" data-action="save"><i class="fas fa-check-circle"></i> {{Sauvegarder}}</a>
                <a class="btn btn-danger eqLogicAction roundedRight" data-action="remove"><i class="fas fa-minus-circle"></i> {{Supprimer}}</a>
            </span>
        </div>
        <ul class="nav nav-tabs" role="tablist">
            <li role="presentation"><a href="#" class="eqLogicAction" aria-controls="home" role="tab" data-toggle="tab" data-action="returnToThumbnailDisplay"><i class="fas fa-arrow-circle-left"></i></a></li>
            <li role="presentation" class="active"><a href="#eqlogictab" aria-controls="home" role="tab" data-toggle="tab"><i class="fas fa-tachometer-alt"></i> {{Équipement}}</a></li>
            <li role="presentation"><a href="#commandtab" aria-controls="profile" role="tab" data-toggle="tab"><i class="fas fa-list"></i> {{Commandes}}</a></li>
        </ul>

        <div class="tab-content">
            <div role="tabpanel" class="tab-pane active" id="eqlogictab">
                <br>
                <form class="form-horizontal">
                    <fieldset>
                        <div class="form-group">
                            <label class="col-sm-3 control-label">{{Nom de la porte de garage}}</label>
                            <div class="col-sm-6">
                                <input type="text" class="eqLogicAttr form-control" data-l1key="id" style="display:none;">
                                <input type="text" class="eqLogicAttr form-control" data-l1key="name" placeholder="{{Nom}}">
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="col-sm-3 control-label">{{Objet parent}}</label>
                            <div class="col-sm-6">
                                <select class="eqLogicAttr form-control" data-l1key="object_id">
                                    <option value="">{{Aucun}}</option>
                                    <?php
                                    foreach ((jeeObject::buildTree(null, false)) as $object) {
                                        echo '<option value="' . $object->getId() . '">' . str_repeat('&nbsp;&nbsp;', $object->getConfiguration('parentNumber')) . $object->getName() . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="col-sm-3 control-label">{{Catégorie}}</label>
                            <div class="col-sm-9">
                                <?php foreach (jeedom::getConfiguration('eqLogic:category') as $key => $value) { ?>
                                    <label class="checkbox-inline">
                                        <input type="checkbox" class="eqLogicAttr" data-l1key="category" data-l2key="<?php echo $key; ?>">
                                        <?php echo $value['name']; ?>
                                    </label>
                                <?php } ?>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="col-sm-3 control-label">{{Options}}</label>
                            <div class="col-sm-9">
                                <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isEnable" checked>{{Activer}}</label>
                                <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isVisible" checked>{{Visible}}</label>
                            </div>
                        </div>

                        <legend><i class="fab fa-apple"></i> {{Apple Home / Homebridge}}</legend>
                        <div class="form-group">
                            <label class="col-sm-3 control-label">{{Mode HomeKit}}<sup><i class="fas fa-question-circle tooltips" title="{{Porte de garage = ouvert/fermé (bouton unique) : fiable, recommandé. Volet = curseur de position, mais avec un moteur à bouton unique sans retour de position le sens ne peut pas être garanti : le positionnement se désynchronise (expérimental). Après changement, sauvegarder puis redémarrer le démon Homebridge.}}"></i></sup></label>
                            <div class="col-sm-4">
                                <select class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="hk_mode">
                                    <option value="garage">{{Porte de garage (recommandé)}}</option>
                                    <option value="volet">{{Volet — position (expérimental)}}</option>
                                </select>
                            </div>
                            <div class="col-sm-5"><span class="help-block" style="margin-top:7px;">{{Bouton unique = mode garage. Le curseur volet nécessite un câblage 2 contacts (OUT1/OUT2) ou un capteur de position.}}</span></div>
                        </div>

                        <legend><i class="fas fa-plug"></i> {{Relais Z-Wave JS (FGBS-222)}}</legend>
                        <div class="alert alert-info">
                            {{Sélectionnez les commandes Z-Wave JS qui pilotent le relais OUT1 câblé sur le bouton du moteur Somfy. Une impulsion = ON puis OFF après la temporisation.}}
                        </div>
                        <div class="form-group">
                            <label class="col-sm-3 control-label">{{Commande relais ON}}<sup><i class="fas fa-question-circle tooltips" title="{{Commande action qui ferme le relais (ex. FGBS-222 : 'OuvertureFermeture', logicalId 37-5-targetValue-true)}}"></i></sup></label>
                            <div class="col-sm-6">
                                <div class="input-group">
                                    <input type="text" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="cmd_pulse_on" placeholder="{{id ou #commande#}}">
                                    <span class="input-group-btn">
                                        <a class="btn btn-default listCmdAction" data-l2key="cmd_pulse_on" title="{{Choisir une commande}}"><i class="fas fa-list-ul"></i></a>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="col-sm-3 control-label">{{Commande relais OFF}}<sup><i class="fas fa-question-circle tooltips" title="{{Commande action qui ouvre le relais (ex. FGBS-222 : 'STOP', logicalId 37-5-targetValue-false). Laisser vide si le relais est en mode auto-off.}}"></i></sup></label>
                            <div class="col-sm-6">
                                <div class="input-group">
                                    <input type="text" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="cmd_pulse_off" placeholder="{{id ou #commande# (optionnel)}}">
                                    <span class="input-group-btn">
                                        <a class="btn btn-default listCmdAction" data-l2key="cmd_pulse_off" title="{{Choisir une commande}}"><i class="fas fa-list-ul"></i></a>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <legend><i class="fas fa-compress-arrows-alt"></i> {{Fins de course (optionnel)}}</legend>
                        <div class="alert alert-info">
                            {{Si un contact (ILS/reed) signale la position fermée et/ou ouverte, le plugin recale l'état réel dès le déclenchement (supprime la dérive de l'estimation). Facultatif — laisser désactivé si non câblé.}}
                        </div>
                        <div class="form-group">
                            <label class="col-sm-3 control-label">{{Utiliser des fins de course}}</label>
                            <div class="col-sm-6" style="padding-top:7px;">
                                <input type="checkbox" class="eqLogicAttr" data-l1key="configuration" data-l2key="use_limits">
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="col-sm-3 control-label">{{Contact « fermé »}}<sup><i class="fas fa-question-circle tooltips" title="{{Commande info binaire active (1) quand la porte est complètement fermée. Optionnel.}}"></i></sup></label>
                            <div class="col-sm-6">
                                <div class="input-group">
                                    <input type="text" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="cmd_limit_closed" placeholder="{{id ou #commande# info (optionnel)}}">
                                    <span class="input-group-btn">
                                        <a class="btn btn-default listInfoAction" data-l2key="cmd_limit_closed" title="{{Choisir une commande}}"><i class="fas fa-list-ul"></i></a>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="col-sm-3 control-label">{{Contact « ouvert »}}<sup><i class="fas fa-question-circle tooltips" title="{{Commande info binaire active (1) quand la porte est complètement ouverte. Optionnel.}}"></i></sup></label>
                            <div class="col-sm-6">
                                <div class="input-group">
                                    <input type="text" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="cmd_limit_open" placeholder="{{id ou #commande# info (optionnel)}}">
                                    <span class="input-group-btn">
                                        <a class="btn btn-default listInfoAction" data-l2key="cmd_limit_open" title="{{Choisir une commande}}"><i class="fas fa-list-ul"></i></a>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <legend><i class="fas fa-sliders-h"></i> {{Temporisation}}</legend>
                        <div class="form-group">
                            <label class="col-sm-3 control-label">{{Durée d'impulsion (ms)}}<sup><i class="fas fa-question-circle tooltips" title="{{Temps entre le ON et le OFF du relais. Typiquement 500 à 800 ms. Mettre 0 si le relais du FGBS-222 est en mode auto-off/momentané : le OFF n'est alors pas envoyé (évite une double impulsion).}}"></i></sup></label>
                            <div class="col-sm-3">
                                <input type="number" min="0" step="50" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="pulse_ms" placeholder="600">
                            </div>
                            <div class="col-sm-6"><span class="help-block" style="margin:7px 0 0;">{{Relais en auto-off ? Mettre 0.}}</span></div>
                        </div>
                        <div class="form-group">
                            <label class="col-sm-3 control-label">{{Délai entre 2 impulsions (ms)}}<sup><i class="fas fa-question-circle tooltips" title="{{Temps entre les deux impulsions envoyées pour inverser le sens (fermer depuis l'ouverture). Le moteur doit avoir le temps d'enregistrer 2 appuis distincts. Typiquement 1000 à 1500 ms.}}"></i></sup></label>
                            <div class="col-sm-3">
                                <input type="number" min="0" step="100" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="double_gap_ms" placeholder="1200">
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="col-sm-3 control-label">{{Temps de course complet (s)}}<sup><i class="fas fa-question-circle tooltips" title="{{Temps qu'il faut à la porte pour aller de fermée à ouverte. Sert au calcul de l'état estimé.}}"></i></sup></label>
                            <div class="col-sm-3">
                                <input type="number" min="1" step="1" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="travel_time" placeholder="18">
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="col-sm-3 control-label">{{Fermeture auto après (s)}}<sup><i class="fas fa-question-circle tooltips" title="{{Si la centrale Somfy referme la porte toute seule après un délai, indiquez-le ici EN SECONDES (le Somfy va de 5 s à 120 s) pour que l'état estimé suive. 0 = désactivé. Le plugin n'envoie AUCUNE impulsion : il reflète juste la fermeture automatique du moteur.}}"></i></sup></label>
                            <div class="col-sm-3">
                                <input type="number" min="0" max="120" step="5" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="auto_close_sec" placeholder="0">
                            </div>
                            <div class="col-sm-5"><span class="help-block" style="margin-top:7px;">{{0 = désactivé. Mettre la même valeur que la temporisation Somfy (ex. 120 pour 2 min).}}</span></div>
                        </div>
                        <div class="form-group">
                            <label class="col-sm-3 control-label">{{Style d'enroulement (widget)}}<sup><i class="fas fa-question-circle tooltips" title="{{Barre fixe : le coffre au-dessus du cadre reste de taille constante. Enroulement qui grossit : le coffre s'épaissit à mesure que le volet s'ouvre.}}"></i></sup></label>
                            <div class="col-sm-4">
                                <select class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="roll_grow">
                                    <option value="0">{{Barre fixe (A)}}</option>
                                    <option value="1">{{Enroulement qui grossit (B)}}</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="col-sm-3 control-label">{{Affichages sur le widget}}<sup><i class="fas fa-question-circle tooltips" title="{{Durée : temps écoulé dans l'état courant (h:m:s). Compte à rebours : temps restant avant la fermeture auto Somfy (nécessite un délai de fermeture auto configuré).}}"></i></sup></label>
                            <div class="col-sm-9">
                                <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="configuration" data-l2key="show_duration">{{Durée dans l'état}}</label>
                                <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="configuration" data-l2key="show_countdown">{{Compte à rebours fermeture auto}}</label>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="col-sm-3 control-label">{{Inverser ouvert/fermé}}<sup><i class="fas fa-question-circle tooltips" title="{{Inverse la sémantique ouvert/fermé partout (widget, état texte, état HomeKit). À activer si l'état affiché est à l'envers par rapport à la réalité.}}"></i></sup></label>
                            <div class="col-sm-6" style="padding-top:7px;">
                                <input type="checkbox" class="eqLogicAttr" data-l1key="configuration" data-l2key="invert">
                            </div>
                        </div>
                        <div class="alert alert-warning">
                            {{Sans capteur de fin de course, l'état affiché est une <b>estimation</b>. Seule la commande <b>Impulsion</b> est physiquement déterministe ; Ouvrir / Fermer / Stop font au mieux selon l'état estimé.}}
                        </div>
                    </fieldset>
                </form>
            </div>

            <div role="tabpanel" class="tab-pane" id="commandtab">
                <a class="btn btn-default btn-sm eqLogicAction pull-right" data-action="addCmd" style="margin-top:5px;"><i class="fas fa-plus-circle"></i> {{Commande}}</a>
                <br><br>
                <div class="table-responsive">
                    <table id="table_cmd" class="table table-bordered table-condensed">
                        <thead>
                            <tr>
                                <th style="min-width:50px;width:70px;">{{ID}}</th>
                                <th style="min-width:200px;">{{Nom}}</th>
                                <th>{{État}}</th>
                                <th style="min-width:80px;width:120px;">{{Options}}</th>
                                <th style="min-width:80px;width:100px;">{{Action}}</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_file('desktop', 'pilotevoletgarage', 'js', 'pilotevoletgarage'); ?>
<?php include_file('core', 'plugin.template', 'js'); ?>
