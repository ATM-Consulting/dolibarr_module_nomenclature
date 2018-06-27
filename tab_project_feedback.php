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

require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
require_once DOL_DOCUMENT_ROOT.'/projet/class/task.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/project.lib.php';
dol_include_once('/nomenclature/class/nomenclature.class.php');
dol_include_once('/commande/class/commande.class.php');

// GET POST
$id = (int)GETPOST('id');
$action=GETPOST('action','alpha');

// Load translation files required by the page
$langs->loadLangs(array('projects', 'companies', 'nomenclature@nomenclature'));
$langs->load('workstation@workstation');

// Initialize technical object to manage hooks of page. Note that conf->hooks_modules contains array of hook context
$hookmanager->initHooks(array('projectfeedbackcard','globalcard'));

$object = new Project($db);

// Load object
//include DOL_DOCUMENT_ROOT.'/core/actions_fetchobject.inc.php';  // Can't use generic include because when creating a project, ref is defined and we dont want error if fetch fails from ref.
if ($id > 0 || ! empty($ref))
{
    $ret = $object->fetch($id,$ref);	// If we create project, ref may be defined into POST but record does not yet exists into database
    if ($ret > 0) {
        $object->fetch_thirdparty();
        $object->fetch_optionals();
        $id=$object->id;
    }
}

// Security check
$socid=GETPOST('socid','int');
//if ($user->societe_id > 0) $socid = $user->societe_id;    // For external user, no check is done on company because readability is managed by public status of project and assignement.
$result = restrictedArea($user, 'projet', $object->id,'projet&project');


/*
 * Actions
 */

$parameters=array();
$reshook=$hookmanager->executeHooks('doActions',$parameters,$object,$action);    // Note that $action and $object may have been modified by some hooks
if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');

if (empty($reshook))
{
    
    
}



/*
 * View 
 */

llxHeader('', $langs->trans('Projectfeedback'));


// To verify role of users
$userAccess = $object->restrictedProjectArea($user,'read');
$userWrite  = $object->restrictedProjectArea($user,'write');
$userDelete = $object->restrictedProjectArea($user,'delete');

$head=project_prepare_head($object);
dol_fiche_head($head, 'projectfeedback', $langs->trans("Project"), -1, ($object->public?'projectpub':'project'));

// Project card

$linkback = '<a href="'.DOL_URL_ROOT.'/projet/list.php?restore_lastsearch_values=1">'.$langs->trans("BackToList").'</a>';

$morehtmlref='<div class="refidno">';
// Title
$morehtmlref.=$object->title;
// Thirdparty
$morehtmlref.='<br>'.$langs->trans('ThirdParty') . ' : ';
if ($object->thirdparty->id > 0)
{
    $morehtmlref .= $object->thirdparty->getNomUrl(1, 'project');
}
$morehtmlref.='</div>';

// Define a complementary filter for search of next/prev ref.
if (! $user->rights->projet->all->lire)
{
    $objectsListId = $object->getProjectsAuthorizedForUser($user,0,0);
    $object->next_prev_filter=" rowid in (".(count($objectsListId)?join(',',array_keys($objectsListId)):'0').")";
}

dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref);



/*
 * NOMENCLATURE
 */

$object_type='commande';
// Get list of order linked to this project
$res = $db->query('SELECT rowid FROM ' . MAIN_DB_PREFIX . 'commande c WHERE c.fk_projet = '.$object->id);
if($res && $res->num_rows>0)
{
    print '<div class="accordion" >';
    while ($obj = $db->fetch_object($res))
    {
        $commande = new Commande($db);
        if($commande->fetch($obj->rowid) > 0){
            _drawlines($commande, $object_type);
        }
    }
    print '</div>';
}


























print '<script>$( function() { $( ".accordion" ).accordion({header: ".accordion-title",  collapsible: true}); } );</script>';

llxFooter();

$db->close();



/*
 * LIB DE FACTORISATION
 */

function _getDetails(&$object, $object_type) {
    global $db,$langs,$conf,$PDOdb,$TProductAlreadyInPage;
    
    $PDOdb = new TPDOdb;
    
    
    $TProduct = array();
    $TWorkstation = array();
    
    foreach($object->lines as $k=>&$line) {
        
        if($line->product_type == 9) continue;
        
        $nomenclature = new TNomenclature;
        $nomenclature->loadByObjectId($PDOdb, $line->id, $object_type, true, $line->fk_product, $line->qty);
        
        $nomenclature->fetchCombinedDetails($PDOdb);
        
        foreach($nomenclature->TNomenclatureDetCombined as $fk_product => $det) {
            
            if(!isset($TProduct[$fk_product])) {
                $TProduct[$fk_product] = $det;
            }
            else{
                $TProduct[$fk_product]->qty += $det->qty;
            }
        }
        
    }
    
    return array($TProduct);
    
    
}

function _drawlines(&$object, $object_type) {
    global $db,$langs,$conf,$PDOdb,$TProductAlreadyInPage;
    

    list($TProduct,$TWorkstation) = _getDetails($object, $object_type);
    
    $langs->load('workstation@workstation');
    
    $formDoli=new Form($db);
    $formCore=new TFormCore;
    
    print '<h3  class="accordion-title">'. $langs->trans('Order') . ' : ' .$object->ref. '</h3>';
    print '<div class="accordion-body-table" >';
    ?>
    
	<table class="border" width="100%">
		<tr class="liste_titre">
			<td class="liste_titre"><?php echo $langs->trans('Product') ?></td>
			<td class="liste_titre" align="center"><?php echo $langs->trans('QtyAllowed') ?></td>
		</tr>
	<?php
		
		dol_include_once('/product/class/product.class.php');
		
		foreach($TProduct as $fk_product=> &$det) {
			
			$product=new Product($db);
			$product->fetch($fk_product);
			
			echo '<tr>
				<td>'.$product->getNomUrl(1).' - '.$product->label.'</td>
				<td align="center">'.price($det->qty).'</td>
			</tr>
			';
			
		}
	
	?>
	</table>
	</div>
	<?php
	
}