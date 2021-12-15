<?php
/* <one line to give the program's name and a brief idea of what it does.>
 * Copyright (C) 2015 ATM Consulting <support@atm-consulting.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * 	\file		admin/nomenclature.php
 * 	\ingroup	nomenclature
 * 	\brief		This file is an example module setup page
 * 				Put some comments here
 */
// Dolibarr environment
//$res = @include("../../main.inc.php"); // From htdocs directory
//if (! $res) {
//    $res = @include("../../../main.inc.php"); // From "custom" directory
//}

// Libraries
require '../config.php';
dol_include_once('/core/lib/admin.lib.php');
dol_include_once('/nomenclature/lib/nomenclature.lib.php');
dol_include_once('/nomenclature/class/nomenclature.class.php');
dol_include_once('abricot/includes/lib/admin.lib.php');
dol_include_once('/product/class/product.class.php');

// Translations
$langs->load("nomenclature@nomenclature");
$PDOdb = new TPDOdb;

// Access control
if (! $user->admin) {
    accessforbidden();
}

// Parameters
$action = GETPOST('action', 'alpha');



/*
 * Actions
 */

if (preg_match('/set_(.*)/',$action,$reg))
{
	$code=$reg[1];
	if (dolibarr_set_const($db, $code, GETPOST($code, 'none'), 'chaine', 0, '', $conf->entity) > 0)
	{
		setEventMessage($langs->trans("ParamSaved"));

		if($code == 'NOMENCLATURE_DETAILS_TAB_REWRITE' && empty($conf->global->PRODUCT_USE_UNITS)){
			// Lorsque la configuration "Séparer les produits des services dans l'onglet de détail des ouvrages" est activée dans nomenclature,
			// activer également la conf cachée "PRODUCT_USE_UNITS" si elle ne l'est pas déjà.
			if (dolibarr_set_const($db, 'PRODUCT_USE_UNITS', 1, 'chaine', 0, '', $conf->entity) > 0)
			{
				setEventMessage($langs->trans("ConfProductUseUnitActivated"));
			}
		}

		header("Location: ".$_SERVER["PHP_SELF"]);
		exit;
	}
	else
	{
		dol_print_error($db);
	}

}

if (preg_match('/del_(.*)/',$action,$reg))
{
	$code=$reg[1];
	if (dolibarr_del_const($db, $code, 0) > 0)
	{
		Header("Location: ".$_SERVER["PHP_SELF"]);
		exit;
	}
	else
	{
		dol_print_error($db);
	}
}

if ($action == 'recalculate_nomenclature'){
    global $db;
    $nome = new TNomenclature();
    $product = new Product($db);
    $nomeIds = $nome->getAllIdsNomenclature();
    foreach ($nomeIds as $nomeId){
        $nome->load($PDOdb,$nomeId);
        $product->fetch($nome->fk_object);
        $nome->setPrice($PDOdb,$nome->qty_reference,$nome->fk_object,'product');
        $nome->updateTotalPR($PDOdb,$product,$nome->totalPR);
    }
    setEventMessage($langs->trans('RecalculateNomenclatureDone'));
    header("Location: ".$_SERVER['PHP_SELF']);
    exit;
}

/*
 * View
 */
$page_name = "nomenclatureSetup";
llxHeader('', $langs->trans($page_name));

// Subheader
$linkback = '<a href="' . DOL_URL_ROOT . '/admin/modules.php">'
    . $langs->trans("BackToModuleList") . '</a>';
print load_fiche_titre($langs->trans($page_name), $linkback);
// Configuration header
$head = nomenclatureAdminPrepareHead();
dol_fiche_head(
    $head,
    'settings',
    $langs->trans("Module104580Name"),
    1,
    "nomenclature@nomenclature"
);

// Setup page goes here
$form=new Form($db);

if(!function_exists('setup_print_title')){
	print '<div class="error" >'.$langs->trans('AbricotNeedUpdate').' : <a href="http://wiki.atm-consulting.fr/index.php/Accueil#Abricot" target="_blank"><i class="fa fa-info"></i> Wiki</a></div>';
	exit;
}

print '<table class="noborder" width="100%">';

// ************************************************
// CONFIGURATION EN LIEN AVEC LES DEVIS / COMMANDES
// ************************************************
setup_print_title('ParamLinkedToOrdersAndPropal');

setup_print_on_off('NOMENCLATURE_ALLOW_FREELINE', $langs->trans('nomenclatureAllowFreeLine'), '', 'nomenclatureAllowFreeLineHelp');
setup_print_on_off('NOMENCLATURE_USE_CUSTOM_THM_FOR_WS', '', '', 'NOMENCLATURE_USE_CUSTOM_THM_FOR_WS_HELP');

if(!empty($conf->global->NOMENCLATURE_DETAILS_TAB_REWRITE) && empty($conf->global->PRODUCT_USE_UNITS)){
	// Lorsque la configuration "Séparer les produits des services dans l'onglet de détail des ouvrages" est activée dans nomenclature,
	// activer également la conf cachée "PRODUCT_USE_UNITS" si elle ne l'est pas déjà.
	// /!\ Voir aussi la partie action
	if (dolibarr_set_const($db, 'PRODUCT_USE_UNITS', 1, 'chaine', 0, '', $conf->entity) > 0)
	{
		setEventMessage($langs->trans("ConfProductUseUnitActivated"));
	}
}
setup_print_on_off('NOMENCLATURE_DETAILS_TAB_REWRITE');

setup_print_on_off('NOMENCLATURE_INCLUDE_PRODUCTS_WITHOUT_NOMENCLATURE');
setup_print_on_off('NOMENCLATURE_SEPARATE_PRODUCT_REF_AND_LABEL');

// **********************************************
// CONFIGURATION EN LIEN AVEC LA GESTION DU STOCK
// **********************************************
setup_print_title('ParamLinkedToStock');

setup_print_on_off('NOMENCLATURE_ALLOW_JUST_MP', $langs->trans('nomenclatureJustMP'), '', 'nomenclatureJustMPHelp');
setup_print_on_off('NOMENCLATURE_ALLOW_MVT_STOCK_FROM_NOMEN');
setup_print_on_off('NOMENCLATURE_HIDE_STOCK_COLUMNS');

// ******************************************************************
// CONFIGURATION EN LIEN AVEC L'AIDE À LA DÉFINITION DU PRIX DE VENTE
// ******************************************************************
setup_print_title('ParamLinkedToSellPrice');
setup_print_on_off('NOMENCLATURE_ACTIVATE_DETAILS_COSTS', '', '', 'NOMENCLATURE_ACTIVATE_DETAILS_COSTS_HELP');
setup_print_on_off('NOMENCLATURE_TAKE_PRICE_FROM_CHILD_FIRST', '', '', 'NOMENCLATURE_TAKE_PRICE_FROM_CHILD_FIRST_HELP');

// Note : hidden by JS
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="action" value="recalculate_nomenclature">';
print '<tr class="recalculate_nomenclature" '.$bc[$var].'>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="right" >';
print $form->textwithtooltip( $langs->trans("RecalculateNomenclatureDesc") , $langs->trans("RecalculateNomenclatureHelp"),2,1,img_help(1,''));
print '</td>';
print '<td align="center"><input type="submit" class="butAction" value="'.$langs->trans("RecalculateNomenclature").'" >';
print '</td></tr>';
print '</form>';


setup_print_on_off('NOMENCLATURE_PERSO_PRICE_HAS_TO_BE_CHARGED', '', '', 'NOMENCLATURE_PERSO_PRICE_HAS_TO_BE_CHARGED_HELP');
if(!empty($conf->global->NOMENCLATURE_PERSO_PRICE_HAS_TO_BE_CHARGED)) {
	setup_print_on_off('NOMENCLATURE_PERSO_PRICE_APPLY_QTY', '', '', 'NOMENCLATURE_PERSO_PRICE_APPLY_QTY_HELP');
}

setup_print_on_off('NOMENCLATURE_HIDE_ADVISED_PRICE','','', 'NOMENCLATURE_HIDE_ADVISED_PRICE_HELP');
setup_print_on_off('NOMENCLATURE_USE_ON_INVOICE','','', 'NOMENCLATURE_USE_ON_INVOICE_HELP');
setup_print_on_off('NOMENCLATURE_USE_SELL_PRICE_INSTEADOF_CALC');
setup_print_on_off('NOMENCLATURE_DONT_USE_NOMENCLATURE_SELL_PRICE','','', 'NOMENCLATURE_DONT_USE_NOMENCLATURE_SELL_PRICE_HELP');
setup_print_on_off('NOMENCLATURE_USE_FLAT_COST_AS_BUYING_PRICE');
setup_print_on_off('NOMENCLATURE_ALLOW_USE_MANUAL_COEF');
setup_print_on_off('NOMENCLATURE_USE_COEF_ON_COUT_REVIENT');
setup_print_on_off('NOMENCLATURE_USE_CUSTOM_BUYPRICE');
setup_print_on_off('NOMENCLATURE_USE_LOSS_PERCENT');
setup_print_on_off('NOMENCLATURE_DONT_RECALCUL_IF_PV_FORCE');

// Prix d'achat/revient suggéré par défaut
$var=!$var;
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print "<input type=\"hidden\" name=\"action\" value=\"set_NOMENCLATURE_COST_TYPE\">";
print '<tr '.$bc[$var].'>';
print '<td>';
print $form->textwithtooltip( $langs->trans("NOMENCLATURE_COST_TYPE") , $langs->trans("NOMENCLATURE_COST_TYPE_HELP"),2,1,img_help(1,''));
print '</td>';
print '<td width="600">';

print '<input id="input_nomenclature_cost_type_1" type="radio" name="NOMENCLATURE_COST_TYPE" value="1" ';
if (!empty($conf->global->NOMENCLATURE_COST_TYPE) && $conf->global->NOMENCLATURE_COST_TYPE === '1') print 'checked ';
print '/> ';
print '<label for="input_nomenclature_cost_type_1" >'.$langs->trans('CostType1').'</label>';

print '<br><input id="input_nomenclature_cost_type_pmp" type="radio" name="NOMENCLATURE_COST_TYPE" value="pmp" ';
if (!empty($conf->global->NOMENCLATURE_COST_TYPE) && $conf->global->NOMENCLATURE_COST_TYPE === 'pmp') print 'checked ';
print '/> ';
print '<label for="input_nomenclature_cost_type_pmp" >'.$langs->trans('CostType2').'</label>';

print '<br><input id="input_nomenclature_cost_type_costprice" type="radio" name="NOMENCLATURE_COST_TYPE" value="costprice" ';
if (!empty($conf->global->NOMENCLATURE_COST_TYPE) && $conf->global->NOMENCLATURE_COST_TYPE === 'costprice') print 'checked ';
print '/> ';
print '<label for="input_nomenclature_cost_type_costprice" >'.$langs->trans('CostType3').'</label>';

print '<br><input id="input_nomenclature_cost_type_disable" type="radio" name="NOMENCLATURE_COST_TYPE" value="disable" ';
if (empty($conf->global->NOMENCLATURE_COST_TYPE) || $conf->global->NOMENCLATURE_COST_TYPE === 'disable') print 'checked ';
print '/> ';
print '<label for="input_nomenclature_cost_type_disable" >'.$langs->trans('Disabled').'</label>';

print '</td>';
print '<td align="center" width="300"><input type="submit" class="butAction" value="'.$langs->trans("Modify").'" >';
print '</td></tr>';
print '</form>';

setup_print_on_off('NOMENCLATURE_APPLY_FULL_COST_NON_SECABLE');



// ***************************************************
// CONFIGURATION EN LIEN AVEC LA GESTION DE PRODUCTION
// ***************************************************
setup_print_title('ParamLinkedToGPAO');

setup_print_on_off('NOMENCLATURE_ALLOW_TO_LINK_PRODUCT_TO_WORKSTATION');
setup_print_on_off('NOMENCLATURE_USE_TIME_BEFORE_LAUNCH');
setup_print_on_off('NOMENCLATURE_USE_TIME_PREPARE');
setup_print_on_off('NOMENCLATURE_USE_TIME_DOING');

// *************************
// CONFIGURATION D'ERGONOMIE
// *************************
setup_print_title('ParamLinkedToUX');
setup_print_on_off('NOMENCLATURE_CLOSE_ON_APPLY_NOMENCLATURE_PRICE');
setup_print_on_off('NOMENCLATURE_CLONE_ON_PRODUCT_CLONE');


// *************************
// CONFIGURATION DIVERS
// *************************
setup_print_title('Parameters');


setup_print_on_off('NOMENCLATURE_USE_QTYREF_TO_ONE'); // , '', '', $langs->trans('NOMENCLATURE_USE_QTYREF_TO_ONE_HELP'));




if (!empty($conf->global->NOMENCLATURE_DETAILS_TAB_REWRITE))
{
	setup_print_on_off('NOMENCLATURE_SHOW_TITLE_IN_COLUMN');
	setup_print_on_off('NOMENCLATURE_HIDE_SUBTOTALS');
	setup_print_on_off('NOMENCLATURE_HIDE_SUBTOTALS_AND_TITLES');
}



setup_print_title('DeprecatedParameters');

setup_print_on_off('NOMENCLATURE_SPEED_CLICK_SELECT', $langs->trans('nomenclatureSpeedSelectClick'), '', $langs->trans('nomenclatureSpeedSelectClickHelp'));

if(!empty($conf->global->PRODUCT_USE_UNITS)) {
	setup_print_on_off('NOMENCLATURE_ALLOW_SELECT_FOR_PRODUCT_UNIT');
}

print '</table>';

?>
<script type="text/javascript">
    $(document).ready(function () {
        <?php
            if ($conf->global->NOMENCLATURE_TAKE_PRICE_FROM_CHILD_FIRST)
                {
                    print '$(".recalculate_nomenclature").show();';
                }
            else{
                    print '$(".recalculate_nomenclature").hide();';
                }
        ?>
    $("#del_NOMENCLATURE_TAKE_PRICE_FROM_CHILD_FIRST").click(function () {
        $(".recalculate_nomenclature").hide();
    });
    $("#set_NOMENCLATURE_TAKE_PRICE_FROM_CHILD_FIRST").click(function () {
        $(".recalculate_nomenclature").show();
    });
    });
</script>

<?php

llxFooter();

$db->close();
