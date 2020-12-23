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


$ajaxConstantOnOffInput = array(
	'alert' => array(
		'del' => array(
			'content'=>$langs->transnoentities('NOMENCLATURE_FEEDBACK_INIT_STOCKConfirmChangeState')
				."<ul>"
				.(!empty($conf->global->NOMENCLATURE_FEEDBACK_INIT_STOCK)?"<li>".$langs->transnoentities('NOMENCLATURE_FEEDBACK_INIT_STOCK')."</li>":'')
				.(!empty($conf->global->NOMENCLATURE_FEEDBACK_LOCK_WAREHOUSE)?"<li>".$langs->transnoentities('NOMENCLATURE_FEEDBACK_LOCK_WAREHOUSE')."</li>":'')
				.(!empty($conf->global->NOMENCLATURE_FEEDBACK_INTO_PROJECT_OVERVIEW)?"<li>".$langs->transnoentities('NOMENCLATURE_FEEDBACK_INTO_PROJECT_OVERVIEW')."</li>":'')
				."</ul>",
			'title'=>$langs->transnoentities('NOMENCLATURE_FEEDBACK_INIT_STOCKConfirmChangeStateTitle')
		)
	),
	'del' => array(
		'NOMENCLATURE_FEEDBACK_INIT_STOCK',
		'NOMENCLATURE_FEEDBACK_LOCK_WAREHOUSE'
	)
);

setup_print_on_off('NOMENCLATURE_FEEDBACK_USE_STOCK', '', '', '', 300, false, $ajaxConstantOnOffInput);

$ajaxConstantOnOffInput = array(
	'alert' => array(
		'set' => array(
			'content' => $langs->transnoentities('NOMENCLATURE_FEEDBACK_USE_STOCK_DependencyChangeState')
				. "<ul><li>" . $langs->transnoentities('NOMENCLATURE_FEEDBACK_USE_STOCK') . "</li></ul>",
			'title' => $langs->transnoentities('NOMENCLATURE_FEEDBACK_USE_STOCK_DependencyChangeStateTitle')
		)
	),
	'set' => array('NOMENCLATURE_FEEDBACK_USE_STOCK' => 1)
);
setup_print_on_off('NOMENCLATURE_FEEDBACK_INIT_STOCK', '', '', '', 300, false, $ajaxConstantOnOffInput);
setup_print_on_off('NOMENCLATURE_FEEDBACK_LOCK_WAREHOUSE', '', '', '', 300, false, $ajaxConstantOnOffInput);

/*
 * Recherche le backport des hook dolibarr dans le fichier projet
 * Dans le cas de l'utilisation sur une version 12 de Dolibarr avec le backport des hooks
 */
$projectOverviewHookExist = false;
$backPortDesc = '';
if(intval(DOL_VERSION) < 13 && file_exists(DOL_DOCUMENT_ROOT.'/projet/element.php')){
 	$stringToFind = 'printOverviewProfit';
	// get the file contents, assuming the file to be readable (and exist)
	$contents = file_get_contents(DOL_DOCUMENT_ROOT.'/projet/element.php');
	if(strpos($contents, $stringToFind) !== false)
	{
		$projectOverviewHookExist = true;
		$backPortDesc = $langs->trans('BackportVxDetectedSoFeatureReady', 'V13');
	}
}

if(intval(DOL_VERSION) > 13 || $projectOverviewHookExist ){
	setup_print_on_off('NOMENCLATURE_FEEDBACK_INTO_PROJECT_OVERVIEW', '', $backPortDesc);

	$available = array (
		'cost_price' => $langs->trans('BasedOnCostPrice'),
		'pmp' => $langs->trans('BasedOnPMP')
	);

	if(!empty($conf->global->NOMENCLATURE_FEEDBACK_USE_STOCK)){
		$available['stock_price'] = $langs->trans('BasedOnStockMovementPrice');
	}

	$selected = $conf->global->NOMENCLATURE_FEEDBACK_COST_BASED;
	if(empty($selected)){ $selected = 'pmp'; }

	$input = $form->selectArray('NOMENCLATURE_FEEDBACK_COST_BASED', $available, $selected);
	setup_print_input_form_part('NOMENCLATURE_FEEDBACK_COST_BASED', '', $backPortDesc, array(), $input, 'NOMENCLATURE_FEEDBACK_COST_BASED_HELP', 400);

}



if(empty($conf->global->NOMENCLATURE_FEEDBACK_OBJECT)){
    dolibarr_set_const($db, 'NOMENCLATURE_FEEDBACK_OBJECT', 'propal', 'chaine', 0, '', $conf->entity);
}

$array = array(
    'propal' => $langs->trans('Proposal'),
    'commande' => $langs->trans('Commande'),
);
$input =$form->selectarray('NOMENCLATURE_FEEDBACK_OBJECT', $array, $conf->global->NOMENCLATURE_FEEDBACK_OBJECT);
setup_print_input_form_part('NOMENCLATURE_FEEDBACK_OBJECT', '', '', array(), $input);



setup_print_title('DeprecatedParameters');
setup_print_on_off('NOMENCLATURE_FEEDBACK_DISPLAY_RENTABILITY');


print '</table>';



llxFooter();

$db->close();
