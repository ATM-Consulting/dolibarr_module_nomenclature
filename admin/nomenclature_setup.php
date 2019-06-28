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
	if (dolibarr_set_const($db, $code, GETPOST($code), 'chaine', 0, '', $conf->entity) > 0)
	{
		setEventMessage($langs->trans("ParamSaved"));

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
print_fiche_titre($langs->trans($page_name), $linkback);

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


print '<table class="noborder" width="100%">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Parameters").'</td>'."\n";
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="center" width="100">'.$langs->trans("Value").'</td>'."\n";
print '</tr>';

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans('nomenclatureAllowFreeLine').'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="center" width="300">';
print ajax_constantonoff('NOMENCLATURE_ALLOW_FREELINE');
print '</td></tr>';
$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans('nomenclatureJustMP').'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="center" width="300">';
print ajax_constantonoff('NOMENCLATURE_ALLOW_JUST_MP');
print '</td></tr>';
$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans('nomenclatureSpeedSelectClick').'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="center" width="300">';
print ajax_constantonoff('NOMENCLATURE_SPEED_CLICK_SELECT');
print '</td></tr>';
$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans('NOMENCLATURE_ACTIVATE_DETAILS_COSTS').'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="center" width="300">';
print ajax_constantonoff('NOMENCLATURE_ACTIVATE_DETAILS_COSTS');
print '</td></tr>';
$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans('NOMENCLATURE_ALLOW_TO_LINK_PRODUCT_TO_WORKSTATION').'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="center" width="300">';
print ajax_constantonoff('NOMENCLATURE_ALLOW_TO_LINK_PRODUCT_TO_WORKSTATION');
print '</td></tr>';
$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans('NOMENCLATURE_TAKE_PRICE_FROM_CHILD_FIRST').'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="center" width="300">';
print ajax_constantonoff('NOMENCLATURE_TAKE_PRICE_FROM_CHILD_FIRST');
print '</td></tr>';


print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="action" value="recalculate_nomenclature">';
print '<tr class="recalculate_nomenclature" '.$bc[$var].'>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="right" width="600">'.$langs->trans("RecalculateNomenclatureDesc");
print '</td>';
print '<td align="center" width="300"><input type="submit" class="butAction" value="'.$langs->trans("RecalculateNomenclature").'" >';
print '</td></tr>';
print '</form>';

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans('NOMENCLATURE_PERSO_PRICE_HAS_TO_BE_CHARGED').'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="center" width="300">';
print ajax_constantonoff('NOMENCLATURE_PERSO_PRICE_HAS_TO_BE_CHARGED');
print '</td></tr>';

if(!empty($conf->global->NOMENCLATURE_PERSO_PRICE_HAS_TO_BE_CHARGED)) {
	$var=!$var;
	print '<tr '.$bc[$var].'>';
	print '<td> &raquo; '.$langs->trans('NOMENCLATURE_PERSO_PRICE_APPLY_QTY').'</td>';
	print '<td align="center" width="20">&nbsp;</td>';
	print '<td align="center" width="300">';
	print ajax_constantonoff('NOMENCLATURE_PERSO_PRICE_APPLY_QTY');
	print '</td></tr>';
}
$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans('NOMENCLATURE_HIDE_ADVISED_PRICE').'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="center" width="300">';
print ajax_constantonoff('NOMENCLATURE_HIDE_ADVISED_PRICE');
print '</td></tr>';

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans('NOMENCLATURE_USE_ON_INVOICE').'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="center" width="300">';
print ajax_constantonoff('NOMENCLATURE_USE_ON_INVOICE');
print '</td></tr>';

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans('NOMENCLATURE_USE_SELL_PRICE_INSTEADOF_CALC').'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="center" width="300">';
print ajax_constantonoff('NOMENCLATURE_USE_SELL_PRICE_INSTEADOF_CALC');
print '</td></tr>';

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans('NOMENCLATURE_DONT_USE_NOMENCLATURE_SELL_PRICE').'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="center" width="300">';
print ajax_constantonoff('NOMENCLATURE_DONT_USE_NOMENCLATURE_SELL_PRICE');
print '</td></tr>';

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans('NOMENCLATURE_USE_QTYREF_TO_ONE').'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="center" width="300">';
print ajax_constantonoff('NOMENCLATURE_USE_QTYREF_TO_ONE');
print '</td></tr>';

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("NOMENCLATURE_USE_CUSTOM_THM_FOR_WS").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="center" width="300">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">'; // Keep form because ajax_constantonoff return single link with <a> if the js is disabled
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="set_NOMENCLATURE_USE_CUSTOM_THM_FOR_WS">';
print ajax_constantonoff('NOMENCLATURE_USE_CUSTOM_THM_FOR_WS');
print '</form>';
print '</td></tr>';

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("NOMENCLATURE_USE_TIME_BEFORE_LAUNCH").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="center" width="300">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">'; // Keep form because ajax_constantonoff return single link with <a> if the js is disabled
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="set_NOMENCLATURE_USE_TIME_BEFORE_LAUNCH">';
print ajax_constantonoff('NOMENCLATURE_USE_TIME_BEFORE_LAUNCH');
print '</form>';
print '</td></tr>';

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("NOMENCLATURE_USE_TIME_PREPARE").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="center" width="300">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">'; // Keep form because ajax_constantonoff return single link with <a> if the js is disabled
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="set_NOMENCLATURE_USE_TIME_PREPARE">';
print ajax_constantonoff('NOMENCLATURE_USE_TIME_PREPARE');
print '</form>';
print '</td></tr>';

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("NOMENCLATURE_USE_TIME_DOING").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="center" width="300">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">'; // Keep form because ajax_constantonoff return single link with <a> if the js is disabled
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="set_NOMENCLATURE_USE_TIME_DOING">';
print ajax_constantonoff('NOMENCLATURE_USE_TIME_DOING');
print '</form>';
print '</td></tr>';

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("NOMENCLATURE_USE_FLAT_COST_AS_BUYING_PRICE").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="center" width="300">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">'; // Keep form because ajax_constantonoff return single link with <a> if the js is disabled
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="NOMENCLATURE_USE_FLAT_COST_AS_BUYING_PRICE">';
print ajax_constantonoff('NOMENCLATURE_USE_FLAT_COST_AS_BUYING_PRICE');
print '</form>';
print '</td></tr>';

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("NOMENCLATURE_ALLOW_USE_MANUAL_COEF").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="center" width="300">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">'; // Keep form because ajax_constantonoff return single link with <a> if the js is disabled
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="NOMENCLATURE_ALLOW_USE_MANUAL_COEF">';
print ajax_constantonoff('NOMENCLATURE_ALLOW_USE_MANUAL_COEF');
print '</form>';
print '</td></tr>';

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("NOMENCLATURE_USE_COEF_ON_COUT_REVIENT").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="center" width="300">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">'; // Keep form because ajax_constantonoff return single link with <a> if the js is disabled
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="NOMENCLATURE_USE_COEF_ON_COUT_REVIENT">';
print ajax_constantonoff('NOMENCLATURE_USE_COEF_ON_COUT_REVIENT');
print '</form>';
print '</td></tr>';

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("NOMENCLATURE_USE_CUSTOM_BUYPRICE").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="center" width="300">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">'; // Keep form because ajax_constantonoff return single link with <a> if the js is disabled
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="NOMENCLATURE_USE_CUSTOM_BUYPRICE">';
print ajax_constantonoff('NOMENCLATURE_USE_CUSTOM_BUYPRICE');
print '</form>';
print '</td></tr>';

if(!empty($conf->global->PRODUCT_USE_UNITS)) {
	$var=!$var;
	print '<tr '.$bc[$var].'>';
	print '<td>'.$langs->trans("NOMENCLATURE_ALLOW_SELECT_FOR_PRODUCT_UNIT").'</td>';
	print '<td align="center" width="300">';
	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">'; // Keep form because ajax_constantonoff return single link with <a> if the js is disabled
	print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
	print '<input type="hidden" name="action" value="NOMENCLATURE_ALLOW_SELECT_FOR_PRODUCT_UNIT">';
	print ajax_constantonoff('NOMENCLATURE_ALLOW_SELECT_FOR_PRODUCT_UNIT');
	print '</form>';
	print '</td></tr>';
}

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("NOMENCLATURE_USE_LOSS_PERCENT").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="center" width="300">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">'; // Keep form because ajax_constantonoff return single link with <a> if the js is disabled
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="NOMENCLATURE_USE_LOSS_PERCENT">';
print ajax_constantonoff('NOMENCLATURE_USE_LOSS_PERCENT');
print '</form>';
print '</td></tr>';

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("NOMENCLATURE_DONT_RECALCUL_IF_PV_FORCE").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="center" width="300">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">'; // Keep form because ajax_constantonoff return single link with <a> if the js is disabled
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="NOMENCLATURE_DONT_RECALCUL_IF_PV_FORCE">';
print ajax_constantonoff('NOMENCLATURE_DONT_RECALCUL_IF_PV_FORCE');
print '</form>';
print '</td></tr>';

$var=!$var;
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print "<input type=\"hidden\" name=\"action\" value=\"set_NOMENCLATURE_COST_TYPE\">";
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("NOMENCLATURE_COST_TYPE").'</td>';
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



$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("NOMENCLATURE_ALLOW_MVT_STOCK_FROM_NOMEN").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="center" width="300">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">'; // Keep form because ajax_constantonoff return single link with <a> if the js is disabled
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="set_NOMENCLATURE_ALLOW_MVT_STOCK_FROM_NOMEN">';
print ajax_constantonoff('NOMENCLATURE_ALLOW_MVT_STOCK_FROM_NOMEN');
print '</form>';
print '</td></tr>';


setup_print_on_off('NOMENCLATURE_CLOSE_ON_APPLY_NOMENCLATURE_PRICE', false, 'NOMENCLATURE_CLOSE_ON_APPLY_NOMENCLATURE_PRICE_help');

setup_print_on_off('NOMENCLATURE_HIDE_STOCK_COLUMNS');


$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("NOMENCLATURE_DETAILS_TAB_REWRITE").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="center" width="300">';
print ajax_constantonoff('NOMENCLATURE_DETAILS_TAB_REWRITE');
print '</td></tr>';

setup_print_on_off('NOMENCLATURE_INCLUDE_PRODUCTS_WITHOUT_NOMENCLATURE');
setup_print_on_off('NOMENCLATURE_SEPARATE_PRODUCT_REF_AND_LABEL');

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
