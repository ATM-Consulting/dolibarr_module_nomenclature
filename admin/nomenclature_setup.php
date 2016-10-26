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
    0,
    "nomenclature@nomenclature"
);

// Setup page goes here
$form=new Form($db);


print '<table class="noborder" width="100%">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Parameters").'</td>'."\n";
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="center" width="100">'.$langs->trans("Value").'</td>'."\n";

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
print '<td>'.$langs->trans('NOMENCLATURE_USE_QTYREF_TO_ONE').'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="center" width="300">';
print ajax_constantonoff('NOMENCLATURE_USE_QTYREF_TO_ONE');
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
print '<label>'.$langs->trans('NomenclatureCreateLabel').'</label>&nbsp;';
print '<input type="text" name="label" value="'.($action == 'add' && !empty($label) ? $label : '').'"  size="25" />&nbsp;&nbsp;';
print '<label>'.$langs->trans('NomenclatureCreateCode').'</label>&nbsp;';
print '<input type="text" name="code_type" value="'.($action == 'add' && !empty($code) ? $code : '').'"  size="15" />&nbsp;&nbsp;';
print '<label>'.$langs->trans('NomenclatureCreateTx').'</label>&nbsp;';
print '<input type="text" name="tx" value="'.($action == 'add' && !empty($tx) ? $tx : '').'"  size="5" />&nbsp;&nbsp;';
print '<input type="submit" class="button" value="'.$langs->trans("Add").'">';
print '</form>';
print '</td></tr>';

print '</table>';



$var=false;
print '<table class="noborder" width="100%">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("ModifyCoef").'</td>'."\n";
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="center" width="100">'.$langs->trans("Value").'</td>'."\n";

foreach ($TCoef as $coef)
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




llxFooter();

$db->close();