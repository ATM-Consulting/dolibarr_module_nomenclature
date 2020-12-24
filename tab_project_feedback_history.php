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
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
dol_include_once('/nomenclature/class/nomenclature.class.php');
dol_include_once('/commande/class/commande.class.php');
dol_include_once('/comm/propal/class/propal.class.php');
dol_include_once('/nomenclature/lib/nomenclature.lib.php');

// GET POST
$id = (int)GETPOST('id', 'int');
$action=GETPOST('action','alpha');

$TQty = GETPOST('qty', 'array');
$fk_entrepot = GETPOST('fk_entrepot', 'int');
$origin = GETPOST('origin', 'aZ09');
$fk_origin = GETPOST('fk_origin', 'int');
$ref = GETPOST('ref', 'aZ09');

$search_date_start = dol_mktime(0, 0, 0, GETPOST('search_date_startmonth', 'int'), GETPOST('search_date_startday', 'int'), GETPOST('search_date_startyear', 'int'));
$search_date_end = dol_mktime(23, 59, 59, GETPOST('search_date_endmonth', 'int'), GETPOST('search_date_endday', 'int'), GETPOST('search_date_endyear', 'int'));
$granularity = GETPOST('granularity', 'alpha');
if(!in_array($granularity, array('DAY', 'MONTH', 'YEAR'))) $granularity = 'MONTH';

// Params for list
$massaction = GETPOST('massaction', 'alpha');
$confirmmassaction = GETPOST('confirmmassaction', 'alpha');
$toselect = GETPOST('toselect', 'array');
$sall = GETPOST('sall');
$button_removefilter_x = GETPOST('button_removefilter_x');
if(!empty($button_removefilter_x)){
	$sall = $search_date_start = $search_date_end = '';
;
}
$nbLine = GETPOST('limit', 'int');
if(empty($nbLine)) $nbLine = !empty($user->conf->MAIN_SIZE_LISTE_LIMIT) ? $user->conf->MAIN_SIZE_LISTE_LIMIT : $conf->global->MAIN_SIZE_LISTE_LIMIT;




// Load translation files required by the page
$langs->loadLangs(array('projects', 'companies', 'nomenclature@nomenclature'));


// Initialize technical object to manage hooks of page. Note that conf->hooks_modules contains array of hook context
$hookmanager->initHooks(array('projectfeedbackhistory'));

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
dol_fiche_head($head, 'projectfeedbackhistory', $langs->trans("Project"), -1, ($object->public?'projectpub':'project'));

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
 * MOUVEMENTS DE STOCKS LIE AU PROJECT
 */

// TODO / ASTUCE :
// utiliser $TOptionalGroupBy pour ajouter des champs à la clause group by issue des champs additionnels (menu burger de la liste)
// ça permettra d'obtenir un "rapport" qui s'adapte aux données

$TOptionalGroupBy = array(
//	'sm.origintype',
//	'sm.fk_origin',
//	'sm.fk_user_author',
//	'sm.fk_entrepot'
);

if($granularity == 'DAY'){
	$granularityFormat = '%Y-%m-%d';
}
elseif($granularity == 'MONTH'){
	$granularityFormat = '%Y-%m';
}
elseif($granularity == 'YEAR'){
	$granularityFormat = '%Y';
}
$sql = 'SELECT DATE_FORMAT(sm.datem, \''.$granularityFormat.'\') AS datemGrouped, sm.fk_product ,p.label productLabel , MIN(sm.datem) minDateM, SUM(sm.value) as qty';

// Modification du sign du pmp en fonction du type de mouvement
// stock movement type  2=output (stock decrease), 3=input (stock increase)
$sql.= ',  SUM(CASE WHEN sm.type_mouvement = 2 THEN -sm.price * sm.value ELSE sm.price * sm.value END) sumPrice  ';



// Add fields from hooks
$parameters=array(
	'sql' => $sql,
	'TOptionalGroupBy' =>& $TOptionalGroupBy // la requette utilise un group by, il est donc plus judicieux d'utiliser $TOptionalGroupBy pour ajouter un simple champs
);
$reshook=$hookmanager->executeHooks('printFieldListSelect', $parameters, $object, $action);    // Note that $action and $object may have been modified by hook
$sql.=$hookmanager->resPrint;


//
$sql.= !empty($TOptionalGroupBy)?', '.implode(', ',$TOptionalGroupBy):'';

$sql.= ' FROM ' . MAIN_DB_PREFIX . 'stock_mouvement sm ';
$sql.= ' JOIN ' . MAIN_DB_PREFIX . 'entrepot e ON (sm.fk_entrepot = e.rowid) ';
$sql.= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'product p ON (sm.fk_product = p.rowid) ';

$sql.= 'WHERE sm.fk_projet = '.$object->id;
$sql.= ' AND sm.type_mouvement IN (2,3) ';
$sql.= ' AND e.entity IN ('.getEntity('stock').') ';
if ($search_date_start) $sql .= " AND sm.datem >= '".$db->idate($search_date_start)."'";
if ($search_date_end)   $sql .= " AND sm.datem <= '".$db->idate($search_date_end)."'";

// Add where from hooks
$parameters=array(
	'sql' => $sql,
	'TOptionalGroupBy' =>& $TOptionalGroupBy
);
$reshook=$hookmanager->executeHooks('printFieldListWhere', $parameters, $object, $action);    // Note that $action and $object may have been modified by hook
$sql.=$hookmanager->resPrint;

$sql.= ' GROUP BY DATE_FORMAT(sm.datem, \''.$granularityFormat.'\'), sm.fk_product ';
$sql.= !empty($TOptionalGroupBy)?', '.implode(', ',$TOptionalGroupBy):'';
// $sql.= ' ORDER BY datemGrouped, sm.fk_product ';

// Todo : Voir si version non groupé : sm.batch, sm.eatby, sm.sellby, <- actuellement aucun interret pour du BTP

$formcore = new TFormCore($_SERVER['PHP_SELF'], 'form_list_webinstance', 'GET');
print '<input type="hidden" name="id" value="'.$object->id.'" />';


$gobackUrl = $_SERVER["PHP_SELF"];


$list = new Listview($db, 'stockmovementhistory');

// Override de la recherche des dates de mouvements de stock
$searchMovement = $langs->trans("DateMovementGranularity").' <label><input type="radio" name="granularity" value="DAY" '.($granularity=='DAY'?'checked':'').' > '.$langs->trans("Day").'</label>';
$searchMovement.= '&nbsp;&nbsp;&nbsp;<label><input type="radio" name="granularity" value="MONTH" '.($granularity=='MONTH'?'checked':'').'  > '.$langs->trans("Month").'</label>';
$searchMovement.= '&nbsp;&nbsp;&nbsp;<label><input type="radio" name="granularity" value="YEAR" '.($granularity=='YEAR'?'checked':'').'  > '.$langs->trans("Year").'</label>';
$searchMovement.=  '<div class="nowrap">';
$searchMovement.=   $langs->trans('From').' ';
$searchMovement.=   $form->selectDate($search_date_start ? $search_date_start : -1, 'search_date_start', 0, 0, 1);
$searchMovement.=   $langs->trans('to').' ';
$searchMovement.=   $form->selectDate($search_date_end ? $search_date_end : -1, 'search_date_end', 0, 0, 1);
$searchMovement.=   '</div>';


// Pour les tries
$paramUrl = '&id='.$object->id;
if (!empty($sall))			$paramUrl .= '&sall='.urldecode($sall);
if (!empty($granularity)) 	$paramUrl .= '&granularity='.$granularity;
if ($search_date_start)		$paramUrl .= '&search_date_start='.urlencode($search_date_start);
if ($search_date_end)   	$paramUrl .= '&search_date_end='.urlencode($search_date_end);

$TParam = array(
		'view_type' => 'list' // default = [list], [raw], [chart]
		,'allow-fields-select' => true
		,'limit'=>array(
			'nbLine' => $nbLine
		)
		,'list' => array(
			'title' => $langs->trans('StockMovementHistory')
//		,'image' => 'stockmovement@nomenclature'
		,'param_url' => $paramUrl
		,'massactions'=>array(
				//'yourmassactioncode'  => $langs->trans('YourMassActionLabel')
			)
		)
		,'math' => array(
//			'sumPrice' => 'groupsum',
//			'CalculatedCost' => 'groupsum',
		)
		,'title' => array(
			'minDateM' => 'MovementDate',
			'fk_product' => 'ProductRef',
			'productLabel' => 'Product',
			'sumPrice' => 'StockCostHT',
			'qty' => 'Qty',
			'CalculatedCost' => 'CalculatedCostHT',
		)
		,'tooltip' => array(
			'sumPrice' => 'StockCostHTHelp',
			'CalculatedCost' => 'CalculatedCostHTHelp',
		)
		,'type' => array(
			'minDateM' => 'date', // [datetime], [hour], [money], [number], [integer]
			'sumPrice' => 'number',
			'CalculatedCost' => 'number',
		)
		,'search' => array(
			'fk_product' => array('search_type' => true, 'table' => array('p'), 'field' => array('ref')) ,// input text de recherche sur plusieurs champs
			'productLabel' => array('search_type' => true, 'table' => array('p'), 'field' => array('label')),
			'minDateM' => array('search_type' => 'override', 'override' => $searchMovement)
		)
		,'eval'=>array(
			//'url' => '_outLink(\'@val@\')',
			'minDateM' => '_getDateGrouped(\'@val@\', \''.$granularity.'\')',
			'fk_product' => '_evalGetObjectOutputField(\'Product\', \'ref\', \'@fk_product@\')',
			'productLabel' => '_evalGetObjectOutputField(\'Product\', \'label\', \'@fk_product@\')',
			'sumPrice' => 'price(\'@val@\')',
			'CalculatedCost' => '_calculatedCost(\'@fk_product@\', \'@qty@\')'
		)
	);

echo $list->render($sql, $TParam);
$formcore->end_form();

//print $sql;
print $list->db->error();

llxFooter();

$db->close();

/**
 * Retourne une date formaté en fonction de de la granularité
 * @param timestamp    $time
 * @param string $granularity MONTH|YEAR|DAY default MONTH
 * @return string
 */
function _getDateGrouped($time = 0, $granularity = 'MONTH'){

	if(empty($time)) return '';

	$format ="%d/%m/%Y";

	if($granularity == 'YEAR'){
		$format ="%Y";
	}
 	elseif($granularity == 'MONTH') {
 		$format ="%m/%Y";
	}

	return  dol_print_date($time, $format);
}



/**
 * auto output object field
 * see WebPassword listviewhelper
 * @param string $objetClassName
 * @param string $key
 * @param int $fk_object
 * @param string $val
 * @return string
 */
function _evalGetObjectOutputField($objetClassName, $key, $fk_object = 0, $val = '')
{
	$object = nomenclature_getObjectFromCache($objetClassName, $fk_object);
	if(!$object && $fk_object>0){ return 'error'; }
	if(!$object){ return ''; }

	$methodVariable = array($object, 'showOutputFieldQuick');
	if(is_callable($methodVariable)){
		return $object->showOutputFieldQuick($key);
	}
	else{
		return _showOutputFieldQuick($object, $key);
	}
}

/**
 * Return HTML string to show a field into a page
 *
 * @param CommonObject $object
 * @param string $key Key of attribute
 * @param string $moreparam To add more parameters on html input tag
 * @param string $keysuffix Prefix string to add into name and id of field (can be used to avoid duplicate names)
 * @param string $keyprefix Suffix string to add into name and id of field (can be used to avoid duplicate names)
 * @param mixed $morecss Value for css to define size. May also be a numeric.
 * @return string
 */
function _showOutputFieldQuick($object, $key, $moreparam = '', $keysuffix = '', $keyprefix = '', $morecss = ''){

	if ($key == 'status' && method_exists($object, 'getLibStatut')){
		return $object->getLibStatut(2); // to fix dolibarr using 3 instead of 2
	}

	return $object->showOutputField($object->fields[$key], $key, $object->{$key}, $moreparam, $keysuffix, $keyprefix, $morecss);
}


/**
 * calculate actual product qty cost with product pmp
 * @param int $fk_product
 * @param int $qty
 * @return string
 */
function _calculatedCost($fk_product = '', $qty = 0)
{
	$product = nomenclature_getObjectFromCache('Product', $fk_product);
	if(empty($product)){
		return '';
	}

	$pmp = $product->pmp;
	if(empty($product->pmp)){
		$pmp = $product->cost_price;
	}

	return price (doubleval($pmp) * doubleval($qty*-1));
}
