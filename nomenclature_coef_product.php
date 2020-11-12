<?php
/* Copyright (C) 2014 Alexis Algoud        <support@atm-conuslting.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 *	\file       /nomenclature/tab_project_feedback.php
 *	\ingroup    projet
 *	\brief      Project feedback
 */


require 'config.php';

require_once DOL_DOCUMENT_ROOT . '/core/lib/product.lib.php';
require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
dol_include_once('/nomenclature/class/nomenclature.class.php');

$type_object = 'product';

// GET POST
$id = (int)GETPOST('id', 'int');
$action=GETPOST('action','alpha');

// Load translation files required by the page
$langs->loadLangs(array('products', 'nomenclature@nomenclature'));


// Initialize technical object to manage hooks of page. Note that conf->hooks_modules contains array of hook context
$hookmanager->initHooks(array('productnomenclaturecoef','globalcard'));

$object = new Product($db);


$PDOdb = new TPDOdb;

// Security check
$fieldvalue = (! empty($id) ? $id : (! empty($ref) ? $ref : ''));
$fieldtype = (! empty($ref) ? 'ref' : 'rowid');
if ($user->societe_id) $socid = $user->societe_id;
$result = restrictedArea($user, 'produit|service', $fieldvalue, 'product&product', '', '', $fieldtype);


// Load object
if ($id > 0 || ! empty($ref))
{
    $object->fetch($id,$ref);
}

if(empty($object->id)){
    exit($langs->trans('ProductNotFound'));
}


/*
 * Actions
 */

$parameters=array();
$reshook=$hookmanager->executeHooks('doActions',$parameters,$object,$action);    // Note that $action and $object may have been modified by some hooks
if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');

if (empty($reshook))
{
    if ($action == 'add' || $action == 'edit')
    {
        $idCoef = GETPOST('rowid', 'int');

        if (GETPOST('delete', 'alpha'))
        {
            $nomenclatureCoef = new TNomenclatureCoefObject;
            $nomenclatureCoef->load($PDOdb, $idCoef);
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

            if ($code && $tx)
            {
                $nomenclatureCoef = new TNomenclatureCoefObject;

                if ($idCoef) $nomenclatureCoef->load($PDOdb, $idCoef);
                else $nomenclatureCoef->type = GETPOST('line_type', 'none');


                $nomenclatureCoef->fk_object = $object->id;
                $nomenclatureCoef->type_object = $type_object;
                //$nomenclatureCoef->label = $label;
                //$nomenclatureCoef->description = $desc;
                $nomenclatureCoef->code_type = $code;
                $nomenclatureCoef->tx_object = $tx;

                $rowid = $nomenclatureCoef->save($PDOdb);

                if ($rowid) setEventMessages($langs->trans('NomenclatureSuccessSaveCoef'), null);
                else setEventMessages($langs->trans('NomenclatureErrorAddCoefDoublon'), null, 'errors');
            }
            else
            {
                setEventMessages($langs->trans('NomenclatureErrorAddCoef'), null, 'errors');
            }
        }

    }

}



/*
 * View
 */


$title = $langs->trans('ProductServiceCard');
$helpurl = '';
$shortlabel = dol_trunc($object->label,16);
if (GETPOST("type", 'none') == '0' || ($object->type == Product::TYPE_PRODUCT))
{
    $title = $langs->trans('Product')." ". $shortlabel ." - ".$langs->trans('CoefList');
    $helpurl='EN:Module_Products|FR:Module_Produits|ES:M&oacute;dulo_Productos';
}
if (GETPOST("type", 'none') == '1' || ($object->type == Product::TYPE_SERVICE))
{
    $title = $langs->trans('Service')." ". $shortlabel ." - ".$langs->trans('CoefList');
    $helpurl='EN:Module_Services_En|FR:Module_Services|ES:M&oacute;dulo_Servicios';
}

llxHeader('', $title, $helpurl);

$head = product_prepare_head($object);
$titre = $langs->trans("CardProduct" . $object->type);
$picto = ($object->type == Product::TYPE_SERVICE ? 'service' : 'product');

dol_fiche_head($head, 'nomenclaturecoef', $titre, -1, $picto);

$linkback = '<a href="'.DOL_URL_ROOT.'/product/list.php?restore_lastsearch_values=1">'.$langs->trans("BackToList").'</a>';
$object->next_prev_filter=" fk_product_type = ".$object->type;

$shownav = 1;
if ($user->societe_id && ! in_array('product', explode(',',$conf->global->MAIN_MODULES_FOR_EXTERNAL))) $shownav=0;

dol_banner_tab($object, 'ref', $linkback, $shownav, 'ref');


print '<div class="fichecenter" >';

/*
 * NOMENCLATURE COEF
 */

$TCoef = TNomenclatureCoefObject::loadCoefObject($PDOdb, $object, $type_object);

$num = !empty($TCoef)?count($TCoef):'';

$backbutton = '';
print_barre_liste($langs->trans("DefaultCoeficientList"), 0, $_SERVER["PHP_SELF"], '', '', '', $backbutton, $num, $num);


/*
print '<table class="noborder" width="100%">';
print '<tr class="liste_titre">';
print '<td colspan="3">'.$langs->trans("AddCoef").'</td>'."\n";

print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("CreateCoef").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="right" width="650">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="add">';
print '<input type="hidden" name="id" value="'.$object->id.'">';
print '<input type="hidden" name="type_object" value="'.$type_object.'">';
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
*/

// Coef lignes nomenclature
$var=false;
print '<table class="noborder" width="100%">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("ModifyCoef").'</td>'."\n";
print '<td align="center" >'.$langs->trans('NomenclatureCreateCode').'</td>';
print '<td align="center" >'.$langs->trans('NomenclatureCreateTx').'</td>';
print '<td align="center" ></td>'."\n";

foreach ($TCoef as $coef)
{

    $allow_to_delete = false;
    if( $coef->code_type!='coef_marge' || ($coef->code_type!='coef_marge' && $coef->rowid > 1) ){
        $allow_to_delete = true;
    }


    $var=!$var;
    print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
    print '<tr '.$bc[$var].'>';
    print '<td>'.$coef->label.'&nbsp;: '.$coef->description.'</td>';
    print '<td align="center" width="20">';
    print '<input readonly="readonly" type="hidden" name="code_type" value="'.$coef->code_type.'"  size="15" />'.$coef->code_type;
    print '</td>';

    print '<td align="center" >';

    print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
    print '<input type="hidden" name="action" value="edit">';
    print '<input type="hidden" name="id" value="'.$object->id.'">';
    print '<input type="hidden" name="type_object" value="'.$type_object.'">';
    print '<input type="hidden" name="rowid" value="'.$coef->rowid.'">';
    print '<input type="text" name="tx" value="'.$coef->tx.'"  size="5" />&nbsp;&nbsp;';

    print '</td>';
    print '<td align="right" >';

    print '<input type="submit" class="button" name="edit" value="'.$langs->trans("Modify").'">&nbsp;';
    if($allow_to_delete) print '<input type="submit" class="button" name="delete" value="'.$langs->trans("Delete").'">';

    print '</td></tr>';
    print '</form>';
}

print '</table>';







print '</div>';

llxFooter();

$db->close();



/*
 * LIB DE FACTORISATION
 */
