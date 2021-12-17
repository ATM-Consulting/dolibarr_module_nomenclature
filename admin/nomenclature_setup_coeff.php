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
require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
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

		// On delete l'extrafield si on delete le coeff
		$e = new ExtraFields($db);
		$e->delete($nomenclatureCoef->code_type, 'propaldet');
		$e->delete($nomenclatureCoef->code_type, 'commandedet');

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
		    // On crée l'extrafield commandedet & propaldet
            if($action == 'add') {
                $e = new ExtraFields($db);
                $e->addExtraField($code, $label, 'price', 100, '24,8', 'propaldet', 0, 0, '', '', 0, '', 0);
                $e->addExtraField($code, $label, 'price', 100, '24,8', 'commandedet', 0, 0, '', '', 0, '', 0);
            }

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
$TCoefFinal = TNomenclatureCoef::loadCoef($PDOdb, 'pricefinal');

if(empty($TCoefFinal)) 	$msg = get_htmloutput_mesg(img_warning('default') . ' ' . 'Ajouter un coefficient du prix de vente conseillé : code "coef_final"', '', 'error', 1);

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
print_fiche_titre($langs->trans($page_name), $linkback);

// Configuration header
$head = nomenclatureAdminPrepareHead();
dol_fiche_head(
    $head,
    'settings_coeff',
    $langs->trans("Module104580Name"),
    1,
    "nomenclature@nomenclature"
);

// Setup page goes here
$form=new Form($db);

print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
$var=false;
print '<table class="noborder" width="100%">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("AddCoef").'</td>'."\n";
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="center" width="100">'.$langs->trans("Value").'</td>'."\n";

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td><strong>'.$langs->trans("CreateCoef").'</strong></br>';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="add">';
print '<label>'.$langs->trans('NomenclatureLineType').'</label>&nbsp;';
print $form->selectarray('line_type', array('nomenclature'=>'Nomenclature', 'workstation'=>$langs->trans('MO'), 'pricefinal'=>$langs->trans('PriceFinal'))).'&nbsp;&nbsp;';
print '<label>'.$langs->trans('NomenclatureCreateLabel').'</label>&nbsp;';
print '<input type="text" name="label" placeholder="'.$langs->trans('NomenclatureCoeffLabel').'" value="'.($action == 'add' && !empty($label) ? $label : '').'"  size="25" /><br />';
print '</td>';
print '<td align="center" >&nbsp;</td>';
print '<td align="right" width="650">';
print '<label>'.$langs->trans('NomenclatureCreateCode').'</label>&nbsp;';
print '<input type="text" name="code_type" value="'.($action == 'add' && !empty($code) ? $code : '').'"  size="15" />&nbsp;&nbsp;';
print '<label>'.$langs->trans('NomenclatureCreateTx').'</label>&nbsp;';
print '<input type="text" name="tx" value="'.($action == 'add' && !empty($tx) ? $tx : '').'"  size="5" />&nbsp;&nbsp;';
print '<input type="submit" class="butAction" value="'.$langs->trans("Add").'">';
print '</td></tr>';

print '</table>';
print '</form>';


// Coef lignes nomenclature
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
	print '<td><input type="text" name="label" placeholder="'.$langs->trans('NomenclatureCoeffLabel').'"  value="'.$coef->label.'"  size="25" />&nbsp;<input type="text" placeholder="'.$langs->trans('NomenclatureCoeffDesc').'"  name="desc" value="'.$coef->description.'" size="60" /></td>';
	print '<td align="center" width="20">&nbsp;</td>';
	print '<td align="right" width="650">';
	print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
	print '<input type="hidden" name="action" value="edit">';
	print '<input type="hidden" name="rowid" value="'.$coef->rowid.'">';
	print '<label>'.$langs->trans('NomenclatureCreateCode').'</label>&nbsp;';
	print '<input placeholder="'.$langs->trans('NomenclatureCoeffCodeType').'"  readonly="readonly" type="text" name="code_type" value="'.$coef->code_type.'"  size="15" />&nbsp;&nbsp;';
	print '<label>'.$langs->trans('NomenclatureCreateTx').'</label>&nbsp;';
	print '<input type="text" name="tx" value="'.$coef->tx.'"  size="5" />&nbsp;&nbsp;';
	print '<input type="submit" class="butAction" name="edit" value="'.$langs->trans("Modify").'">&nbsp;';
    print '<input type="submit" class="butActionDelete" name="delete" value="'.$langs->trans("Delete").'">';
	print '</td></tr>';
	print '</form>';
}

print '</table>';



// Coef lignes msin d'oeuvre (module workstation)
if(!empty($conf->workstationatm->enabled)) {

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
		print '<td><input type="text" placeholder="'.$langs->trans('NomenclatureCoeffLabel').'"  name="label" value="'.$coef->label.'"  size="25" />&nbsp;<input type="text"  placeholder="'.$langs->trans('NomenclatureCoeffDesc').'"  name="desc" value="'.$coef->description.'" size="60" /></td>';
		print '<td align="center" width="20">&nbsp;</td>';
		print '<td align="right" width="650">';
		print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
		print '<input type="hidden" name="action" value="edit">';
		print '<input type="hidden" name="rowid" value="'.$coef->rowid.'">';
		print '<label>'.$langs->trans('NomenclatureCreateCode').'</label>&nbsp;';
		print '<input readonly="readonly" type="text" name="code_type" value="'.$coef->code_type.'"  size="15" />&nbsp;&nbsp;';
		print '<label>'.$langs->trans('NomenclatureCreateTx').'</label>&nbsp;';
		print '<input type="text" name="tx" value="'.$coef->tx.'"  size="5" />&nbsp;&nbsp;';
		print '<input type="submit" class="butAction" name="edit" value="'.$langs->trans("Modify").'">&nbsp;';
		print '<input type="submit" class="butActionDelete" name="delete" value="'.$langs->trans("Delete").'">';
		print '</td></tr>';
		print '</form>';
	}

	print '</table>';

}

// Coef prix de vente conseillé

$var=false;
print '<table class="noborder" width="100%">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("ModifyCoefFinal").'</td>'."\n";
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="center" width="100">'.$langs->trans("Value").'</td>'."\n";

foreach ($TCoefFinal as &$coef)
{

    $allow_to_delete = ($coef->code_type!='coef_final');

    $var=!$var;
    print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
    print '<tr '.$bc[$var].'>';
    print '<td><input type="text" placeholder="'.$langs->trans('NomenclatureCoeffLabel').'"  name="label" value="'.$coef->label.'"  size="25" />&nbsp;<input type="text"  placeholder="'.$langs->trans('NomenclatureCoeffDesc').'"  name="desc" value="'.$coef->description.'" size="60" /></td>';
    print '<td align="center" width="20">&nbsp;</td>';
    print '<td align="right" width="650">';
    print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
    print '<input type="hidden" name="action" value="edit">';
    print '<input type="hidden" name="rowid" value="'.$coef->rowid.'">';
    print '<label>'.$langs->trans('NomenclatureCreateCode').'</label>&nbsp;';
    print '<input readonly="readonly" type="text" name="code_type" value="'.$coef->code_type.'"  size="15" />&nbsp;&nbsp;';
    print '<label>'.$langs->trans('NomenclatureCreateTx').'</label>&nbsp;';
    print '<input type="text" name="tx" value="'.$coef->tx.'"  size="5" />&nbsp;&nbsp;';
    print '<input type="submit" class="butAction" name="edit" value="'.$langs->trans("Modify").'">&nbsp;';
    if($allow_to_delete) print '<input type="submit" class="butActionDelete" name="delete" value="'.$langs->trans("Delete").'">';
    print '</td></tr>';
    print '</form>';
}

print '</table>';

if(!empty($msg)) print $msg;

llxFooter();

$db->close();
