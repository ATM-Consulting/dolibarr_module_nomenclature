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

	if (GETPOST('delete', 'alpha'))
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
			else $nomenclatureCoef->type = GETPOST('line_type', 'none');

			$nomenclatureCoef->label = $label;
			$nomenclatureCoef->description = $desc;
			$nomenclatureCoef->code_type = $code;
			$nomenclatureCoef->tx = $tx;
			$nomenclatureCoef->entity = $conf->entity;

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
	if (dolibarr_set_const($db, $code, GETPOST($code, 'none'), 'chaine', 0, '', $conf->entity) > 0)
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
print load_fiche_titre($langs->trans($page_name), $linkback);

// Configuration header
$head = nomenclatureAdminPrepareHead();
dol_fiche_head(
    $head,
    'settings_btp',
    $langs->trans("Module104580Name"),
    1,
    "nomenclature@nomenclature"
);

// Setup page goes here
$form=new Form($db);


print '<table class="noborder" width="100%">';

setup_print_title('Projectfeedback');

setup_print_on_off('NOMENCLATURE_FEEDBACK');

setup_print_on_off('NOMENCLATURE_FEEDBACK_USE_STOCK');
setup_print_on_off('NOMENCLATURE_FEEDBACK_INTO_PROJECT_OVERVIEW');

setup_print_on_off('NOMENCLATURE_FEEDBACK_LOCK_WAREHOUSE');

setup_print_on_off('NOMENCLATURE_FEEDBACK_INIT_STOCK');


if(empty($conf->global->NOMENCLATURE_FEEDBACK_OBJECT)){
    dolibarr_set_const($db, 'NOMENCLATURE_FEEDBACK_OBJECT', 'propal', 'chaine', 0, '', $conf->entity);
}
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
print '<input type="submit" value="'.$langs->trans("Modify").'" class="butAction">';
print '</form>';
print '</td></tr>';




setup_print_title('DeprecatedParameters');
setup_print_on_off('NOMENCLATURE_FEEDBACK_DISPLAY_RENTABILITY');


print '</table>';



llxFooter();

$db->close();
