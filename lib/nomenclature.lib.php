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
 *	\file		lib/nomenclature.lib.php
 *	\ingroup	nomenclature
 *	\brief		This file is an example module library
 *				Put some comments here
 */

function nomenclatureAdminPrepareHead()
{
    global $langs, $conf;

    $langs->load("nomenclature@nomenclature");

    $h = 0;
    $head = array();

    $head[$h][0] = dol_buildpath("/nomenclature/admin/nomenclature_setup.php", 1);
    $head[$h][1] = $langs->trans("Parameters");
    $head[$h][2] = 'settings';
    $h++;
	
    $head[$h][0] = dol_buildpath("/nomenclature/admin/import.php", 1);
    $head[$h][1] = $langs->trans("Import");
    $head[$h][2] = 'import';
    $h++;
    $head[$h][0] = dol_buildpath("/nomenclature/admin/nomenclature_about.php", 1);
    $head[$h][1] = $langs->trans("About");
    $head[$h][2] = 'about';
    $h++;

    // Show more tabs from modules
    // Entries must be declared in modules descriptor with line
    //$this->tabs = array(
    //	'entity:+tabname:Title:@nomenclature:/nomenclature/mypage.php?id=__ID__'
    //); // to add new tab
    //$this->tabs = array(
    //	'entity:-tabname:Title:@nomenclature:/nomenclature/mypage.php?id=__ID__'
    //); // to remove a tab
    complete_head_from_modules($conf, $langs, $object, $head, $h, 'nomenclature');

    return $head;
}


function cloneNomenclatureFromProduct(&$PDOdb, $fk_product, $fk_object, $object_type, $json = false)
{
	$TNomen = TNomenclature::get($PDOdb, $fk_product,false, 'product');
    foreach($TNomen as &$n) {
        
        $n->reinit();
        $n->fk_object = $fk_object;
        $n->object_type = $object_type;
        $n->save($PDOdb);
    }
    
    if (!$json) setEventMessage('NomenclatureCloned');
}

function _updateObjectLine(&$n, $object_type, $fk_object, $fk_origin, $apply_nomenclature_price=false)
{
	global $db;

	if (! empty($apply_nomenclature_price))
	{
		switch ($object_type) {
			case 'propal':
				dol_include_once('/comm/propal/class/propal.class.php');
				
				$propal = new Propal($db);
				$propal->fetch($fk_origin);
				
				foreach ($propal->lines as $line)
				{
					if ($line->id == $fk_object)
					{
						$propal->updateline($fk_object, $n->getSellPrice($line->qty), $line->qty, $line->remise_percent, $line->tva_tx, $line->localtax1_tx, $line->localtax2_tx, $line->desc, 'HT', $line->info_bits, $line->special_code, $line->fk_parent_line, $line->skip_update_total, $line->fk_fournprice, $n->getBuyPrice($line->qty), $line->product_label, $line->product_type, $line->date_start, $line->date_end, $line->array_options, $line->fk_unit);
						$line->array_options['options_pv_force'] = false;
						$line->insertExtraFields();
					}
				}

				break;
			case 'commande':
				dol_include_once('/commande/class/commande.class.php');

				$commande = new Commande($db);
				$commande->fetch(GETPOST('fk_origin', 'int'));

				foreach ($commande->lines as $line)
				{
					if ($line->id == $fk_object)
					{
						$commande->updateline($fk_object, $line->desc, $n->getSellPrice($line->qty), $line->qty, $line->remise_percent, $line->tva_tx, $line->localtax1_tx, $line->localtax2_tx, 'HT', $line->info_bits, $line->date_start, $line->date_end, $line->product_type, $line->fk_parent_line, $line->skip_update_total, $line->fk_fournprice, $n->getBuyPrice($line->qty), $line->product_label, $line->special_code, $line->array_options, $line->fk_unit);
					}
				}
				break;
		}

	}
}


function getFormConfirmNomenclature(&$form, &$product, $fk_nomenclature_used, $action, $qty_reference=1)
{
    global $langs,$db;
    
	$formconfirm = '';
	if ($action == 'create_stock')
    {
		require_once DOL_DOCUMENT_ROOT.'/product/class/html.formproduct.class.php';
		
		$qty = GETPOST('nomenclature_qty_to_create', 'int');
		if (empty($qty)) $qty = $qty_reference;
		
		$formproduct = new FormProduct($db);
		$formproduct->loadWarehouses($product->id, '', '', true);
		
        $text = '';
		$formquestion = array(
			array('type' => 'hidden'	,'name' => 'fk_product'					,'value' => $product->id)
			,array('type' => 'hidden'	,'name' => 'fk_nomenclature_used'		,'value' => $fk_nomenclature_used)
			,array('type' => 'text'		,'name' => 'nomenclature_qty_to_create'	,'label' => $langs->trans('NomenclatureHowManyQty')				,'value' => $qty, 'moreattr' => 'size="5"')
			,array('type' => 'other'	,'name' => 'fk_warehouse_to_make'		,'label' => $langs->trans('NomenclatureSelectWarehouseToMake')	,'value' => $formproduct->selectWarehouses(GETPOST('fk_warehouse_to_make'), 'fk_warehouse_to_make', 'warehouseopen,warehouseinternal', 0, 0, 0, '', 0, 0, null, 'minwidth200'))
			,array('type' => 'other'	,'name' => 'fk_warehouse_needed'		,'label' => $langs->trans('NomenclatureSelectWarehouseNeeded')	,'value' => $formproduct->selectWarehouses(GETPOST('fk_warehouse_needed'), 'fk_warehouse_needed', 'warehouseopen,warehouseinternal', 0, 0, 0, '', 0, 0, null, 'minwidth200'))
		);
		
        $formconfirm = $form->formconfirm($_SERVER['PHP_SELF'] . '?fk_product=' . $product->id, $langs->trans('NomenclatureCreateStock', $product->ref), $text, 'confirm_create_stock', $formquestion, 0, 1, 'auto');
    }
    
    return $formconfirm;
}




function feedback_getDetails(&$object, $object_type) {
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

function feedback_drawlines(&$object, $object_type, $TParam = array(), $editMode = false) {
    global $db,$langs,$conf,$PDOdb,$TProductAlreadyInPage;
    
    $fk_entrepot = GETPOST('fk_entrepot', 'int');
    
    dol_include_once('/product/class/product.class.php');
    
    list($TProduct,$TWorkstation) = feedback_getDetails($object, $object_type);
    
    $langs->load('workstation@workstation');
    $PDOdb = new TPDOdb;
    $formDoli=new Form($db);
    
    
    if(empty($TParam['action'])){
        $TParam['action'] = $_SERVER['PHP_SELF'];
    }
    
    
    
    print '<form name="'.$object_type.'-'.$object->id.'" action="'.$TParam['action'].'"  method="post" >';
    

    
    
    if(!empty($TParam['hiddenFields'])){
        foreach ($TParam['hiddenFields'] as $fiefldName => $fiefldParam){
            
            if(!empty($fiefldParam['output'])){
                print $fiefldParam['output'];
            }
            else {
                
                if(is_array($fiefldParam)){
                    $value = $fiefldParam['value'];
                }
                else{
                    $value = $fiefldParam;
                }
                
                print '<input type="hidden" name="'.$fiefldName.'" value="'.$value.'"  />';
            }
        }
    }
    
    print '<table class="border" width="100%">';
    print '	<tr class="liste_titre">';
    print '		<td class="liste_titre">'.$langs->trans('Product').'</td>';
    print '		<td class="liste_titre" align="center">'.$langs->trans('QtyAllowed').'</td>';
    print '		<td class="liste_titre" align="center">'.$langs->trans('QtyReturn').'</td>';
    print '	</tr>';
    
    
    if($editMode && !empty($conf->global->NOMENCLATURE_FEEDBACK_USE_STOCK))
    {
        dol_include_once('product/class/html.formproduct.class.php');
        $formproduct=new FormProduct($db);
        
        print '	<tr>';
        print '		<td class="liste_titre"></td>';
        print '		<td class="liste_titre" align="center"></td>';
        print '		<td class="liste_titre" align="center">';
        print $formproduct->selectWarehouses($fk_entrepot,'fk_entrepot');
        print '		</td>';
        print '	</tr>';
    }
    
    foreach($TProduct as $fk_product=> &$det) {
        
        $product=new Product($db);
        $product->fetch($fk_product);
        
        $feedback = new TNomenclatureFeedback();
        $feedback->loadByProduct($PDOdb, $object_type, $object->id, $det->fk_product, $det->fk_nomenclature);
        
        print '<tr>';
        print '   <td>'.$product->getNomUrl(1).' - '.$product->label.'</td>';
        print '   <td align="center">'.price($det->qty).'</td>';
        
        
        print '   <td align="center">';
        
        if($editMode){
            print '<input type="number" min="0" max="'.$det->qty.'" name="qty['.$det->fk_nomenclature.']['.$det->fk_product.']" data-id="'.$feedback->id.'" value="'.$feedback->qty.'" /></td>';
        }
        else{
            print $feedback->qty;
        }
        print '</tr>';
        
    }
    
    
    print '</table>';
    if($editMode){
        print '<p class="right">';
        print '<button type="submit" name="action" value="save" class="butAction"  >'.$langs->trans('Save').'</button>';
        print '</p>';
    }
    print '</form>';
    
    
    
}

function saveFeedbackForm($origin=false){
    
    global $langs, $conf, $db, $user;
    
    $TQty = GETPOST('qty', 'array');
    $fk_entrepot = GETPOST('fk_entrepot', 'int');
    $origin = GETPOST('origin', 'aZ09');
    $fk_origin = GETPOST('fk_origin', 'int');
    
    $countError = 0;
    $countSave  = 0;
    
    if($conf->stock->enabled){
        dol_include_once('product/stock/class/mouvementstock.class.php');
    }
    
    if(!empty($TQty))
    {
        $PDOdb = new TPDOdb;
        foreach ( $TQty as $fk_nomenclature => $TProduct){
            
            foreach ( $TProduct as $fk_product => $qty){
                $feedback = new TNomenclatureFeedback();
                if(!$feedback->loadByProduct($PDOdb, $origin, $fk_origin, $fk_product, $fk_nomenclature)){
                    
                    $feedback->fk_nomenclature  = $fk_nomenclature;
                    $feedback->fk_product       = $fk_product;
                    $feedback->fk_origin        = $fk_origin;
                    $feedback->origin           = $origin;
                    $feedback->note             = '';
                }
                
                // Store last qty for stock movements
                $lastQty = $feedback->qty ;
                $feedback->qty = $qty;
                
                if($feedback->save($PDOdb))
                {
                    
                    
                    $errors = array();
                    
                    if(!empty($conf->global->NOMENCLATURE_FEEDBACK_USE_STOCK) && !empty($conf->stock->enabled) && !empty($fk_entrepot)){
                        $mouvementStock = new MouvementStock($db);
                        
                        if(!empty($origin)){
                            $mouvementStock->origin = $origin;
                        }
                        
                        $qtyDelta = abs($feedback->qty - $lastQty) ;
                        
                        if(!empty($qtyDelta)){
                            $label = $langs->trans('nomenclatureStockFeedback');
                            if($lastQty < $feedback->qty){
                                $mouvementStock->reception($user, $fk_product, $fk_entrepot, $qtyDelta, 0, $label);
                            }
                            else{
                                $mouvementStock->livraison($user, $fk_product, $fk_entrepot, $qtyDelta, 0, $label);
                            }
                        }
                    }
                    
                    
                    $countSave ++;
                }
                else {
                    $countError ++;
                }
            }
            
        }
    }
    
    if($countSave>0){
        setEventMessage($langs->trans('FeedbackSaved', $countSave));
    }
    
    if($countError>0){
        setEventMessage($langs->trans('FeedBackSaveErrors', $countError), 'errors');
    }
    
    if(empty($countSave) && empty($countError)){
        setEventMessage($langs->trans('NothingWasDo'), 'warnings');
    }
}