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

dol_include_once('/nomenclature/class/nomenclature.class.php');

$type_object = 'product';

// GET POST
$action=GETPOST('action','alpha');

// Load translation files required by the page
$langs->loadLangs(array('products', 'nomenclature@nomenclature'));


// Initialize technical object to manage hooks of page. Note that conf->hooks_modules contains array of hook context
$hookmanager->initHooks(array('nomenclatureList','globallist'));


$PDOdb = new TPDOdb;


// LIST ELEMENTS 
$limit = GETPOST('limit','int')?GETPOST('limit','int'):$conf->liste_limit;
$sortfield = GETPOST("sortfield",'alpha');
$sortorder = GETPOST("sortorder",'alpha');
$page = (GETPOST("page",'int')?GETPOST("page", 'int'):0);
$type = (GETPOST("type",'alpha')?GETPOST("type", 'alpha'):0);
if (empty($page) || $page == -1) { $page = 0; }     // If $page is not defined, or '' or -1
$offset = $limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;
if (! $sortfield) $sortfield="n.title";
if (! $sortorder) $sortorder="ASC";



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


$title = $langs->trans('Nomenclatures');
$arrayofjs = array('/core/js/listview.js');
llxHeader('', $title, '', '', 0, 0, $arrayofjs);


$shownav = 1;
if ($user->societe_id && ! in_array('nomenclature', explode(',',$conf->global->MAIN_MODULES_FOR_EXTERNAL))) $shownav=0;


// CONFIGURATION DU LISTVIEW
$list = new Listview($db,'nomenclature-list');

$TParam= array();

$TParam['list'] = array(
    'title'=>$langs->trans('NomenclatureList'),
);
$TParam['limit'] = array(
            'page'=>(isset($_REQUEST['page']) ? $_REQUEST['page'] : 0),
            'nbLine'=>$limit
        );
$TParam['list']['param_url'] = 'limit='.$limit;
$TParam['title'] = array(
    //'object_type' => $langs->trans('ObjectType'),
    'ref' => $langs->trans('ProductRef'),
    'title' => $langs->trans('NomenclatureName'),
    
    'is_default'  => $langs->trans('is_default'),
    'qty_reference' => $langs->trans('nomenclatureQtyReference'),
    'SellPrice'     => $langs->trans('PriceConseil'),
    //'totalPRCMO_PMP'  => $langs->trans('TotalAmountCostWithChargePMPQty'),
    'totalPRCMO_OF'  => $langs->trans('TotalAmountCostWithChargeOFQty'),
    //'totalPRCMO'  => $langs->trans('Total'),
    
    'date_cre' => $langs->trans('CreateOn'),
    'date_maj' => $langs->trans('Updated'),
);

$TParam['hide'] = array('is_default');


// defined list align for field
$TParam['position'] = array(
        'text-align'=> array(
            'ref' => 'left',
            'title' => 'left',
            
            'is_default'  => 'center',
            
            'qty_reference' => 'right',
            'SellPrice'     => 'right',
            'totalPRCMO_PMP'  => 'right',
            'totalPRCMO_OF'  => 'right',
            'totalPRCMO'  => 'right',
            
            'date_cre' => 'center',
            'date_maj' => 'center',
        )
);

$TParam['allow-fields-select'] = 1 ;// allow to select hidden fields


$TParam['sortfield'] = 'title';
$TParam['sortorder'] = 'ASC';

$TParam['type'] = array (
    'date_cre' => 'date', // [datetime], [hour], [money], [number], [integer]
    'date_maj' => 'datetime',
    'qty_reference' => 'number',
    'totalPRCMO_PMP' => 'money',
    'totalPRCMO_OF'  => 'money',
    'totalPRCMO'     => 'money',
);

$TParam['search'] = array (
    'object_type'    => array('search_type'=> _objectTypeList(), 'table'=>'n', 'fieldname'=>'object_type' ),
    'title'          => array('search_type'=>true, 'table'=>'n', 'fieldname'=>'title'),
    'ref'            => array('search_type'=>true, 'table'=>'p', 'fieldname'=>'ref'),
    'is_default'     => array('search_type'=>array(0=>$langs->trans('No'), 1=>$langs->trans('Yes') )),
    
    'qty_reference'  => array('search_type'=>true, 'table'=>'n', 'fieldname'=>'qty_reference'),
    'totalPRCMO_PMP' => array('search_type'=>true, 'table'=>'n', 'fieldname'=>'totalPRCMO_PMP'),
    'totalPRCMO_OF'  => array('search_type'=>true, 'table'=>'n', 'fieldname'=>'totalPRCMO_OF'),
    //'totalPRCMO'     => array('search_type'=>true, 'table'=>'n', 'fieldname'=>'totalPRCMO'),
    'date_cre'       => array('search_type'=>true, 'table'=>'n', 'fieldname'=>'date_cre'),
    'date_maj'       => array('search_type'=>true, 'table'=>'n', 'fieldname'=>'date_maj'),
);

$TParam['eval'] = array(
    // array of customized field function
    'title'=>'linkToNomenclature(\'@val@\', @rowid@, \'@object_type@\', \'@fk_object@\')',
    'ref'=>'linkToNomenclature(\'@val@\', @rowid@, \'@object_type@\', \'@fk_object@\')',
    'is_default' => '_yesNo(\'@is_default@\')',
    
    
    // prix d'achat total
    //'BuyPrice' => 'nomenclature_getBuyPrice(@rowid@)',
    
    // prix de vente conseillé total
    'SellPrice' => 'nomenclature_getSellPrice(@rowid@)',
    
    // Prix de revient chargé (on affiche tjr le chargé)
    'totalPRCMO' => 'nomenclature_totalPRCMO(@rowid@)',
    
    // Coût de revient chargé théorique pour %s exemplaire(s)
    'totalPRCMO_PMP' => 'nomenclature_totalPRCMO_PMP(@rowid@)',
    
    // TotalAmountCostWithChargeOF=Coût de revient chargé réel pour %s exemplaire(s)
    'totalPRCMO_OF' => 'nomenclature_totalPRCMO(@rowid@)',//'nomenclature_totalPRCMO_OF(@rowid@)',
    
    
    
);

// Query MYSQL
$sql = "SELECT p.ref, n.rowid, n.is_default, n.title, n.date_maj, n.date_cre, n.fk_object, n.object_type, n.totalPRCMO_PMP, n.totalPRCMO_OF, n.totalPRCMO, n.qty_reference ";
$sql.= " FROM `" . MAIN_DB_PREFIX . "nomenclature` n   ";
$sql.= " JOIN `" . MAIN_DB_PREFIX . "product` p ON (p.rowid = n.fk_object)   ";
$sql.= " WHERE  n.object_type = 'product' AND n.fk_nomenclature_parent = 0 ";


// le formulaire
print '<div class="fichecenter" >';

print '<form action="'.$_SERVER["PHP_SELF"].'" method="post" name="formulaire">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="formfilteraction" id="formfilteraction" value="list">';
print '<input type="hidden" name="action" value="list">';
print '<input type="hidden" name="sortfield" value="'.$sortfield.'">';
print '<input type="hidden" name="sortorder" value="'.$sortorder.'">';
print '<input type="hidden" name="page" value="'.$page.'">';
print '<input type="hidden" name="type" value="'.$type.'">';


print $list->render($sql, $TParam);

print '</form>';


print '</div>';




llxFooter();

$db->close();



/*
 * LIB DE FACTORISATION
 */
function _objectTypeList(){
    global $db;
    
    $sql = "SELECT DISTINCT object_type FROM `" . MAIN_DB_PREFIX . "nomenclature`";
    $TResult = array();
    $res = $db->query($sql);
    if ($res)
    {
        while ($obj = $db->fetch_object($res))
        {
            $TResult[$obj->object_type] = $obj->object_type;
        }
        return $TResult;
    }
    else { dol_print_error($db); }
    return $TResult;
}

function linkToNomenclature($label = '',$nomenclature_id = '', $object_type = '', $object_id = '' ){
    
    if(empty($object_type)) return $label;
    
    if($object_type == 'product'){
        $url = dol_buildpath('nomenclature/nomenclature.php',1).'?fk_nomenclature='.intval($nomenclature_id ).'&amp;fk_product='.intval($object_id).'#nomenclature'.intval($nomenclature_id );
    }
    else {
        $url = dol_buildpath('nomenclature/nomenclature-detail.php',1).'?id='.intval($object_id).'&amp;object='.$object_type;
    }
    
    return '<a href="'.$url.'" target="_self" >'.(empty($label)?'N/A':$label).'</label>';
}

function _yesNo($id=0){
    global $langs;
    
    $array = array(0=>$langs->trans('No'), 1=>$langs->trans('Yes') );
    if(!empty($array[$id])) return $array[$id];
    return $array[0];
}

function nomenclature_cache($id=0, $usecache=1){
    
    global $PDOdb, $n_cache;
    
    if(!empty($id) ){
        
        if($usecache && !empty($n_cache[$id])){
            return $n_cache[$id];
        }
        
        
        $n=new TNomenclature ;
        if($n->load($PDOdb, $id)){
            $n->setPrice($PDOdb,$n->qty_reference, $n->fk_object, $n->object_type);
            $n_cache[$id]=$n;
            return $n;
        }
        else{
            return 0;
        }
    }
    else{
        return 0;
    }
    
}


// prix d'achat total
function nomenclature_getBuyPrice($id=0){
    $n = nomenclature_cache($id);
    if(!empty($n)){
        return price($n->getBuyPrice());
    }
    
    return '--';
}


// prix de vente conseillé total
function nomenclature_getSellPrice($id=0){
    $n = nomenclature_cache($id);
    if(!empty($n)){
        return price($n->getSellPrice());
    }
    
    return '--';
}


// Prix de revient chargé (on affiche tjr le chargé)
function nomenclature_totalPRCMO($id=0){
    $n = nomenclature_cache($id);
    if(!empty($n)){
        return price(price2num($n->totalPRCMO,'MT'));
    }
    
    return '--';
}

// Prix de revient chargé 
function nomenclature_totalPR($id=0){
    $n = nomenclature_cache($id);
    if(!empty($n)){
        return price(price2num($n->totalPR,'MT'));
    }
    
    return '--';
}


// TotalAmountCostWithChargePMP=Coût de revient chargé théorique pour %s exemplaire(s)
function nomenclature_totalPRCMO_PMP($id=0){
    $n = nomenclature_cache($id);
    if(!empty($n)){
        return price(price2num($n->totalPRCMO_PMP,'MT'));
    }
    
    return '--';
}

// TotalAmountCostWithChargeOF=Coût de revient chargé réel pour %s exemplaire(s)
function nomenclature_totalPRCMO_OF($id=0){
    $n = nomenclature_cache($id);
    if(!empty($n)){
        return price(price2num($n->totalPRCMO_OF,'MT'));
    }
    
    return '--';
}









