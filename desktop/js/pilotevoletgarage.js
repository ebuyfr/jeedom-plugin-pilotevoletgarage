/* Plugin "Pilote Volet Garage" — JS de la page de configuration */

/* Rendu d'une ligne de commande dans l'onglet "Commandes".
 * Fonction requise par le template Jeedom ; ici affichage minimal en
 * lecture seule car les commandes sont créées automatiquement. */
function addCmdToTable(_cmd) {
    if (!isset(_cmd)) {
        var _cmd = {configuration: {}};
    }
    if (!isset(_cmd.configuration)) {
        _cmd.configuration = {};
    }
    var tr = '<tr class="cmd" data-cmd_id="' + init(_cmd.id) + '">';
    tr += '<td class="hidden-xs">';
    tr += '<span class="cmdAttr" data-l1key="id"></span>';
    tr += '</td>';
    tr += '<td>';
    tr += '<div class="row">';
    tr += '<div class="col-sm-6">';
    tr += '<input class="cmdAttr form-control input-sm" data-l1key="name">';
    tr += '</div>';
    tr += '<div class="col-sm-6">';
    tr += '<span class="type" type="' + init(_cmd.type) + '">' + jeedom.cmd.availableType() + '</span>';
    tr += '<span class="subType" subType="' + init(_cmd.subType) + '"></span>';
    tr += '</div>';
    tr += '</div>';
    tr += '</td>';
    tr += '<td>';
    tr += '<span class="cmdAttr" data-l1key="htmlstate"></span>';
    tr += '</td>';
    tr += '<td>';
    tr += '<input type="checkbox" class="cmdAttr" data-l1key="isVisible" checked/> {{Visible}}<br/>';
    tr += '<input type="checkbox" class="cmdAttr" data-l1key="isHistorized"/> {{Historiser}}';
    tr += '</td>';
    tr += '<td>';
    if (is_numeric(_cmd.id)) {
        tr += '<a class="btn btn-default btn-xs cmdAction expertModeVisible" data-action="configure"><i class="fas fa-cogs"></i></a> ';
        tr += '<a class="btn btn-default btn-xs cmdAction" data-action="test"><i class="fas fa-rss"></i> {{Tester}}</a>';
    }
    tr += '</td>';
    tr += '</tr>';
    $('#table_cmd tbody').append(tr);
    var cmd = $('#table_cmd tbody .cmd').last();
    cmd.setValues(_cmd, '.cmdAttr');
    if (isset(_cmd.type)) {
        cmd.find('.cmdAttr[data-l1key=type]').value(init(_cmd.type));
    }
    if (isset(_cmd.subType)) {
        cmd.find('.cmdAttr[data-l1key=subType]').value(init(_cmd.subType));
    }
    jeedom.cmd.changeType(cmd, init(_cmd.subType));
}

/* Sélecteur de commande Z-Wave JS pour les champs relais ON / OFF.
 * Ouvre la modale de choix de commande et stocke la chaîne humaine. */
$('body').off('click', '.listCmdAction').on('click', '.listCmdAction', function () {
    var l2key = $(this).attr('data-l2key');
    jeedom.cmd.getSelectModal({cmd: {type: 'action'}}, function (result) {
        $('.eqLogicAttr[data-l2key=' + l2key + ']').value(result.human);
    });
});

/* Sélecteur de commande INFO (contacts de fin de course). */
$('body').off('click', '.listInfoAction').on('click', '.listInfoAction', function () {
    var l2key = $(this).attr('data-l2key');
    jeedom.cmd.getSelectModal({cmd: {type: 'info'}}, function (result) {
        $('.eqLogicAttr[data-l2key=' + l2key + ']').value(result.human);
    });
});
