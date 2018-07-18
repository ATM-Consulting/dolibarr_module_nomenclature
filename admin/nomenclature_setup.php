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

// Translations
$langs->load("nomenclature@nomenclature");
$PDOdb = new TPDOdb;

// Access control
if (! $user->admin) {
    accessforbidden();
}

// Parameters
$action = GETPOST('action', 'alpha');

if ($action == 'add' || $action == 'edit')
{
	$id = GETPOST('rowid', 'int');

	if (GETPOST('delete'))
	{
		$nomenclatureCoef = new TNomenclatureCoef;
		$nomenclatureCoef->load($PDOdb, $id);
		$res = $nomenclatureCoef->delete($PDOdb);

		if ($res > 0) setEventMessages($langs->trans('NomenclatureDeleteSuccess'), null);
		else setEventMessages($langs->trans('NomenclatureErrorCantDelete'), null, 'errors');
	}
	else
	{
		$label = GETPOST('label', 'alpha');
		$desc = GETPOST('desc', 'alpha');
		$code = GETPOST('code_type', 'alpha');
		$tx = GETPOST('tx', 'alpha');

		if ($label && $code && $tx)
		{
			$nomenclatureCoef = new TNomenclatureCoef;

			if ($id) $nomenclatureCoef->load($PDOdb, $id);
			else $nomenclatureCoef->type = GETPOST('line_type');
			
			$nomenclatureCoef->label = $label;
			$nomenclatureCoef->description = $desc;
			$nomenclatureCoef->code_type = $code;
			$nomenclatureCoef->tx = $tx;

			$rowid = $nomenclatureCoef->save($PDOdb);

			if ($rowid) setEventMessages($langs->trans('NomenclatureSuccessAddCoef'), null);
			else setEventMessages($langs->trans('NomenclatureErrorAddCoefDoublon'), null, 'errors');
		}
		else
		{
			setEventMessages($langs->trans('NomenclatureErrorAddCoef'), null, 'errors');
		}
	}

}

$TCoef = TNomenclatureCoef::loadCoef($PDOdb);
$TCoefWS = TNomenclatureCoef::loadCoef($PDOdb, 'workstation');

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
	print '<td align="center" width="20">&nbsp;</td>';
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
print '<input type="radio" name="NOMENCLATURE_COST_TYPE" value="1" ';
if (!empty($conf->global->NOMENCLATURE_COST_TYPE) && $conf->global->NOMENCLATURE_COST_TYPE === '1') print 'checked ';
print '/> ';
print $langs->trans('CostType1');
print '<br><input type="radio" name="NOMENCLATURE_COST_TYPE" value="pmp" ';
if (!empty($conf->global->NOMENCLATURE_COST_TYPE) && $conf->global->NOMENCLATURE_COST_TYPE === 'pmp') print 'checked ';
print '/> ';
print $langs->trans('CostType2');
print '<br><input type="radio" name="NOMENCLATURE_COST_TYPE" value="costprice" ';
if (!empty($conf->global->NOMENCLATURE_COST_TYPE) && $conf->global->NOMENCLATURE_COST_TYPE === 'costprice') print 'checked ';
print '/> ';
print $langs->trans('CostType3');
print '<br><input type="radio" name="NOMENCLATURE_COST_TYPE" value="disable" ';
if (empty($conf->global->NOMENCLATURE_COST_TYPE) || $conf->global->NOMENCLATURE_COST_TYPE === 'disable') print 'checked ';
print '/> ';
print $langs->trans('Disabled');
print '</td>';
print '<td align="center" width="300"><input type="submit" class="button" value="'.$langs->trans("Modify").'" class="button">';
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



_print_title('Projectfeedback');

_print_on_off('NOMENCLATURE_FEEDBACK');

_print_on_off('NOMENCLATURE_FEEDBACK_USE_STOCK');

_print_on_off('NOMENCLATURE_FEEDBACK_LOCK_WAREHOUSE');

_print_on_off('NOMENCLATURE_FEEDBACK_INIT_STOCK');



$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("NOMENCLATURE_FEEDBACK_OBJECT").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="center" width="300">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">'; // Keep form because ajax_constantonoff return single link with <a> if the js is disabled
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="set_NOMENCLATURE_FEEDBACK_OBJECT">';
$array = array(
    'propal' => $langs->trans('Proposal'),
    'commande' => $langs->trans('Commande'),
);
print $form->selectarray('NOMENCLATURE_FEEDBACK_OBJECT', $array, $conf->global->NOMENCLATURE_FEEDBACK_OBJECT);
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'" class="button">';
print '</form>';
print '</td></tr>';


print '</table>';

$var=false;
print '<table class="noborder" width="100%">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("AddCoef").'</td>'."\n";
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="center" width="100">'.$langs->trans("Value").'</td>'."\n";

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("CreateCoef").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="right" width="650">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="add">';
print '<label>'.$langs->trans('NomenclatureLineType').'</label>&nbsp;';
print $form->selectarray('line_type', array('nomenclature'=>'Nomenclature', 'workstation'=>$langs->trans('MO'))).'&nbsp;&nbsp;';
print '<label>'.$langs->trans('NomenclatureCreateLabel').'</label>&nbsp;';
print '<input type="text" name="label" value="'.($action == 'add' && !empty($label) ? $label : '').'"  size="25" /><br />';
print '<label>'.$langs->trans('NomenclatureCreateCode').'</label>&nbsp;';
print '<input type="text" name="code_type" value="'.($action == 'add' && !empty($code) ? $code : '').'"  size="15" />&nbsp;&nbsp;';
print '<label>'.$langs->trans('NomenclatureCreateTx').'</label>&nbsp;';
print '<input type="text" name="tx" value="'.($action == 'add' && !empty($tx) ? $tx : '').'"  size="5" />&nbsp;&nbsp;';
print '<input type="submit" class="button" value="'.$langs->trans("Add").'">';
print '</form>';
print '</td></tr>';

print '</table>';


// Coef lignes nomenclature
$var=false;
print '<table class="noborder" width="100%">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("ModifyCoef").'</td>'."\n";
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="center" width="100">'.$langs->trans("Value").'</td>'."\n";

foreach ($TCoef as $coef)
{
	
	$allow_to_delete = ($coef->code_type!='coef_marge');
	
	
	$var=!$var;
	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
	print '<tr '.$bc[$var].'>';
	print '<td><input type="text" name="label" value="'.$coef->label.'"  size="25" />&nbsp;<input type="text" name="desc" value="'.$coef->description.'" size="60" /></td>';
	print '<td align="center" width="20">&nbsp;</td>';
	print '<td align="right" width="650">';
	print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
	print '<input type="hidden" name="action" value="edit">';
	print '<input type="hidden" name="rowid" value="'.$coef->rowid.'">';
	print '<label>'.$langs->trans('NomenclatureCreateCode').'</label>&nbsp;';
	print '<input readonly="readonly" type="text" name="code_type" value="'.$coef->code_type.'"  size="15" />&nbsp;&nbsp;';
	print '<label>'.$langs->trans('NomenclatureCreateTx').'</label>&nbsp;';
	print '<input type="text" name="tx" value="'.$coef->tx.'"  size="5" />&nbsp;&nbsp;';
	print '<input type="submit" class="button" name="edit" value="'.$langs->trans("Modify").'">&nbsp;';
	if($allow_to_delete) print '<input type="submit" class="button" name="delete" value="'.$langs->trans("Delete").'">';
	print '</td></tr>';
	print '</form>';
}

print '</table>';



// Coef lignes msin d'oeuvre (module workstation)
if(!empty($conf->workstation->enabled)) {

	$var=false;
	print '<table class="noborder" width="100%">';
	print '<tr class="liste_titre">';
	print '<td>'.$langs->trans("ModifyCoefWS").'</td>'."\n";
	print '<td align="center" width="20">&nbsp;</td>';
	print '<td align="center" width="100">'.$langs->trans("Value").'</td>'."\n";
	
	foreach ($TCoefWS as &$coef)
	{
		
		$var=!$var;
		print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
		print '<tr '.$bc[$var].'>';
		print '<td><input type="text" name="label" value="'.$coef->label.'"  size="25" />&nbsp;<input type="text" name="desc" value="'.$coef->description.'" size="60" /></td>';
		print '<td align="center" width="20">&nbsp;</td>';
		print '<td align="right" width="650">';
		print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
		print '<input type="hidden" name="action" value="edit">';
		print '<input type="hidden" name="rowid" value="'.$coef->rowid.'">';
		print '<label>'.$langs->trans('NomenclatureCreateCode').'</label>&nbsp;';
		print '<input readonly="readonly" type="text" name="code_type" value="'.$coef->code_type.'"  size="15" />&nbsp;&nbsp;';
		print '<label>'.$langs->trans('NomenclatureCreateTx').'</label>&nbsp;';
		print '<input type="text" name="tx" value="'.$coef->tx.'"  size="5" />&nbsp;&nbsp;';
		print '<input type="submit" class="button" name="edit" value="'.$langs->trans("Modify").'">&nbsp;';
		print '<input type="submit" class="button" name="delete" value="'.$langs->trans("Delete").'">';
		print '</td></tr>';
		print '</form>';
	}
	
	print '</table>';

}



llxFooter();

$db->close();



function _print_title($title="")
{
    global $langs;
    print '<tr class="liste_titre">';
    print '<td>'.$langs->trans($title).'</td>'."\n";
    print '<td align="center" width="20">&nbsp;</td>';
    print '<td align="center" ></td>'."\n";
    print '</tr>';
}

function _print_on_off($confkey, $title = false, $desc ='')
{
    global $var, $bc, $langs, $conf;
    $var=!$var;
    
    print '<tr '.$bc[$var].'>';
    print '<td>'.($title?$title:$langs->trans($confkey));
    if(!empty($desc))
    {
        print '<br><small>'.$langs->trans($desc).'</small>';
    }
    print '</td>';
    print '<td align="center" width="20">&nbsp;</td>';
    print '<td align="center" width="300">';
    print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
    print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
    print '<input type="hidden" name="action" value="set_'.$confkey.'">';
    print ajax_constantonoff($confkey);
    print '</form>';
    print '</td></tr>';
}

function _print_input_form_part($confkey, $title = false, $desc ='', $metas = array(), $type='input', $help = false)
{
    global $var, $bc, $langs, $conf, $db;
    $var=!$var;
    
    $form=new Form($db);
    
    $defaultMetas = array(
        'name' => $confkey
    );
    
    if($type!='textarea'){
        $defaultMetas['type']   = 'text';
        $defaultMetas['value']  = $conf->global->{$confkey};
    }
    
    
    $metas = array_merge ($defaultMetas, $metas);
    $metascompil = '';
    foreach ($metas as $key => $values)
    {
        $metascompil .= ' '.$key.'="'.$values.'" ';
    }
    
    print '<tr '.$bc[$var].'>';
    print '<td>';
    
    if(!empty($help)){
        print $form->textwithtooltip( ($title?$title:$langs->trans($confkey)) , $langs->trans($help),2,1,img_help(1,''));
    }
    else {
        print $title?$title:$langs->trans($confkey);
    }
    
    if(!empty($desc))
    {
        print '<br><small>'.$langs->trans($desc).'</small>';
    }
    
    print '</td>';
    print '<td align="center" width="20">&nbsp;</td>';
    print '<td align="right" width="300">';
    print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
    print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
    print '<input type="hidden" name="action" value="set_'.$confkey.'">';
    if($type=='textarea'){
        print '<textarea '.$metascompil.'  >'.dol_htmlentities($conf->global->{$confkey}).'</textarea>';
    }
    else {
        print '<input '.$metascompil.'  />';
    }
    
    print '<input type="submit" class="butAction" value="'.$langs->trans("Modify").'">';
    print '</form>';
    print '</td></tr>';
}