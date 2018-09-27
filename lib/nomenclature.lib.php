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
        $coef_qty_price = $nomenclature->setPrice($PDOdb, $nomenclature->qty_reference, '', $object_type, $object->id,$line->fk_product);
        
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
    
    $qtyConsume = GETPOST('qtyConsume');
    
    dol_include_once('/product/class/product.class.php');
    dol_include_once('product/class/html.formproduct.class.php');
    
    list($TProduct,$TWorkstation) = feedback_getDetails($object, $object_type);
    
    $langs->load('workstation@workstation');
    $PDOdb = new TPDOdb;
    
    
    if(empty($TParam['action'])){
        $TParam['action'] = $_SERVER['PHP_SELF'];
    }
    
    
    
    print '<form id="'.$object_type.'-'.$object->id.'" name="'.$object_type.'-'.$object->id.'" action="'.$TParam['action'].'"  method="post" >';
    

    
    
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
    print '<tbody>';
    
    // une astuce pour etre sur d'avoir les produits en premier suivis des services
    $TProductsClassed = array();
    foreach($TProduct as $fk_product=> $det) {
        $product = getProductNomenclatureCache($fk_product); // mise en cache des produits car utilisé plus tard
        $fk_product_type = 4;
        if($product){$fk_product_type = $product->type; } // récupération du type, par defaut 0 -> produit
        $TProductsClassed[intval($fk_product_type)][$fk_product] = $det;
    }
    ksort ( $TProductsClassed );
    
    unset($TProduct);
    
    $TtotalDefault = array(
        'calculate_price' => 0,
        'charged_price' => 0,
        'price' => 0,
        'feedback_qtyUsed' => 0,
        'feedback_stockAllowed' => 0,
        'feedback_diffqty' => 0,
        'qty' => 0,
    );
    $Ttotal = $TtotalDefault;
    
    foreach ($TProductsClassed as $fk_product_type => $TProduct) {
    
        if(empty($TProduct)){
            continue;
        }
        
        print_feedback_drawlines_lineHead($editMode,$fk_product_type,$fk_entrepot);
        
        // init des totaux
        $TtotalType = $TtotalDefault;
        
        foreach($TProduct as $fk_product=> &$det) {
            
            $product = getProductNomenclatureCache($fk_product);
            //if(empty($product)){continue;}
            
            
            $feedback = new TNomenclatureFeedback();
            $resfecth = $feedback->loadByProduct($PDOdb, $object_type, $object->id, $det->fk_product, $det->fk_nomenclature);
           
            
            $firstStockMovement= false;
            if(!$resfecth){
                $firstStockMovement = true; // nécessite un mouvement de stock vers le chantier
            }
            
            $class = '';
            if($conf->global->NOMENCLATURE_FEEDBACK_INIT_STOCK && $firstStockMovement && $editMode && !empty($conf->global->NOMENCLATURE_FEEDBACK_USE_STOCK) ){
                $class = 'stockisnotinit';
                $legende = true; // affiche la légende en bas
            }
            
            $domKeySuffix = '-'.$det->fk_nomenclature.'-'.$det->fk_product;
            $dataKey = 'data-targetkey="'.$domKeySuffix.'"';
            
            print '<tr class="'.$class.'" id="line'.$domKeySuffix.'" data-stockAllowed="'.$feedback->stockAllowed.'"  data-qtyused="'.$feedback->qtyUsed.'" '.$dataKey.'  >';
            print '   <td>'.$product->getNomUrl(1).' - '.$product->label.'</td>';
            print '   <td align="center">'.price($det->qty).'</td>';
            print '   <td align="left">'.$product->getLabelOfUnit().'</td>';
            
            
            print '   <td align="left">';
            if(!empty($conf->global->NOMENCLATURE_FEEDBACK_USE_STOCK) && !empty($conf->global->NOMENCLATURE_FEEDBACK_INIT_STOCK)){
                
                if($editMode){
                    print img_picto($langs->trans('ApplyPlanned'),'rightarrow', 'class="loadAllowed" '.$dataKey.' ');
                    $qtyConsumeValue = 0;//!empty($qtyConsume[$det->fk_nomenclature][$det->fk_product])?$qtyConsume[$det->fk_nomenclature][$det->fk_product]:0;
                    
                    print '<input class="stockAllowed" id="stockAllowed'.$domKeySuffix.'" '.$dataKey.'  type="number" min="'.$feedback->qtyUsed.'" name="stockAllowed['.$det->fk_nomenclature.']['.$det->fk_product.']" data-id="'.$feedback->id.'" value="'.$feedback->stockAllowed.'" />';
                    
                }
                else{
                    print price($feedback->stockAllowed);
                }
                
                print ' <span class="qty-used-impact" id="qty-allowed-impact'.$domKeySuffix.'" ></span>';
                print '<br/>';
                
            }
            
            
            
            if($editMode && !empty($conf->global->NOMENCLATURE_FEEDBACK_USE_STOCK) && $product->type == 0 && ( empty($feedback->fk_warehouse) || empty($conf->global->NOMENCLATURE_FEEDBACK_LOCK_WAREHOUSE)) )
            {
                $formproduct=new FormProduct($db);
                print $formproduct->selectWarehouses($fk_entrepot,'entrepot-'.$det->fk_nomenclature.'-'.$det->fk_product,'warehouseopen',0,0,$det->fk_product);
            }
            elseif(!empty($conf->global->NOMENCLATURE_FEEDBACK_USE_STOCK) && $product->type == 0 && !empty($feedback->fk_warehouse))
            {
                $entrepot = getEntrepotNomenclatureCache($feedback->fk_warehouse);
                if($entrepot){
                    print '<small>'.$entrepot->libelle.'</small>';
                }
            }
            
            print '	</td>';
            
            
            
            
            print '   <td align="center">'.price($feedback->qtyUsed).' <span class="qty-used-impact" id="qty-used-impact'.$domKeySuffix.'" ></span></td>';
            
            if($conf->global->NOMENCLATURE_FEEDBACK_INIT_STOCK && !empty($conf->global->NOMENCLATURE_FEEDBACK_USE_STOCK) ){
                
                $dispo = $feedback->stockAllowed - $feedback->qtyUsed;
                $class= '';
                if( $dispo < 0){
                    $class= 'error';
                }
                print '<td  align="center" ><span class="'.$class.'"  >'.price($feedback->stockAllowed - $feedback->qtyUsed).'</span>';
                print ' <span class="qty-used-impact" id="qty-diff-impact'.$domKeySuffix.'" ></span></td>';
            }
            
            print '   <td align="left">';
            
            if($editMode){
                
                print '<input id="start-qty'.$domKeySuffix.'"  type="hidden" name="start-qty['.$det->fk_nomenclature.']['.$det->fk_product.']"  value="'.$det->qty.'" />';
                print '<input id="diff-qty'.$domKeySuffix.'"  type="hidden" name="diff-qty['.$det->fk_nomenclature.']['.$det->fk_product.']"  value="'.($det->qty - $feedback->qtyUsed).'" />';
                
                
                print img_picto($langs->trans('ApplyPlanned'),'rightarrow', 'class="loadPlanned" '.$dataKey.' ');
                $qtyConsumeValue = 0;//!empty($qtyConsume[$det->fk_nomenclature][$det->fk_product])?$qtyConsume[$det->fk_nomenclature][$det->fk_product]:0;
                
                print '<input class="qtyConsume" id="qty-consume'.$domKeySuffix.'" '.$dataKey.'  type="number" min="'.(-$feedback->qtyUsed).'" max="'.$det->qtyConsume.'" name="qtyConsume['.$det->fk_nomenclature.']['.$det->fk_product.']" data-id="'.$feedback->id.'" value="'.$qtyConsumeValue.'" />';
            
                
            }
            else{
                print price($feedback->qtyUsed);
            }
            print '</td>';
            
            
            if(!empty($conf->global->NOMENCLATURE_FEEDBACK_DISPLAY_RENTABILITY))
            {
               
                $calculate_price = price2num($det->calculate_price,'MT');
                $price_charge = price2num($det->charged_price,'MT');
                print '<td class="liste_titre" align="center">'.price($calculate_price, 0, '', 1, -1, -1, 'auto').'</td>';
                print '<td class="liste_titre" align="center">'.price($price_charge, 0, '', 1, -1, -1, 'auto').'</td>';
                print '<td class="liste_titre" align="center">'.price($det->price, 0, '', 1, -1, -1, 'auto').'</td>';
                
                $TtotalType['calculate_price'] += $calculate_price;
                $TtotalType['charged_price'] += $price_charge;
                $TtotalType['price'] += $det->price;
                $TtotalType['feedback_qtyUsed'] += $feedback->qtyUsed;
                $TtotalType['feedback_stockAllowed'] += $feedback->stockAllowed;
                $TtotalType['feedback_diffqty'] += ($feedback->stockAllowed - $feedback->qtyUsed);
                $TtotalType['qty'] += $det->qty;
            
            }
            
            
            print '</tr>';
            
            
        }
        
        if(!empty($conf->global->NOMENCLATURE_FEEDBACK_DISPLAY_RENTABILITY))
        {
            // ajout de $TtotalType dans $Ttotal
            foreach( $Ttotal as $key => $value){
                if(in_array($key, $TtotalType)){
                    $Ttotal[$key] += $TtotalType[$key];
                }
            }
            
            print '<tr class="liste_total"  >';
            
            $colspan = 6;
            if($conf->global->NOMENCLATURE_FEEDBACK_INIT_STOCK && !empty($conf->global->NOMENCLATURE_FEEDBACK_USE_STOCK) ){
                $colspan++;
            }
            
            print '<td class="liste_total_cell"  align="right" colspan="'.$colspan.'" >'.$langs->trans('Total').'</td>';
    
            print '<td class="liste_total_cell" align="center">'.price($TtotalType['calculate_price'], 0, '', 1, -1, -1, 'auto').'</td>';
            print '<td class="liste_total_cell" align="center">'.price($TtotalType['charged_price'], 0, '', 1, -1, -1, 'auto').'</td>';
            print '<td class="liste_total_cell" align="center">'.price($TtotalType['price'], 0, '', 1, -1, -1, 'auto').'</td>';
            print '</tr>';
        }
        
        
        
        if($editMode && !empty($conf->global->NOMENCLATURE_FEEDBACK_USE_STOCK) && !empty($conf->global->NOMENCLATURE_FEEDBACK_INIT_STOCK) && $fk_product_type === 0)
        {
            //print '<tfooter>';
            print '<tr>';
            print '<td class="liste_titre" colspan="4" ></td>';
            print '<td class="liste_titre" align="center"><span class="pointer DoStockFeedBack" ><i class="fa fa-recycle"></i> '.$langs->trans('DoStockFeedBack').'</span></td>';
            print '<td class="liste_titre"  ></td>';
            print '</tr>';
            //print '</tfooter>';
        }
        else{
            
        }
        
        
    }
    print '</tbody>';
    print '</table>';
    
    
    
    
    
    
    if($editMode){
        print '<p class="right">';
        print '<button type="submit" name="action" value="save" class="butAction"  >'.$langs->trans('Save').'</button>';
        print '</p>';
    }
    print '</form>';
    
    // Parfois, les légendes c'est bien ;-)
    if(!empty($legende) ){
        print '<fieldset>';
        print '<legend>'.$langs->trans('Legend').'</legend>';
        print '<span class="stockisnotinit" ></span>';
        print $langs->trans('StockNotInit');
        print '</fieldset>';
    }
    
    
    
    print '<script type="text/javascript" src="'.dol_buildpath('nomenclature/js/feedback.js',2).'"  ></script>';
    
}

function print_feedback_drawlines_lineHead($editMode,$fk_product_type){
    global $langs,$conf;
    
    if($fk_product_type === 0){
        $productTypeTitle = $langs->trans('Products');
    }elseif($fk_product_type === 1){
        $productTypeTitle = $langs->trans('Services');
    }
    else{
        $productTypeTitle = $langs->trans('Others');
    }

    print '<tr class="liste_titre">';
    $nbCols = 4;
    print '<td class="liste_titre">'.$productTypeTitle.'</td>';
    print '<td class="liste_titre" align="center" colspan="2">'.$langs->trans('QtyPlanned').'</td>';
    
    if(!empty($conf->global->NOMENCLATURE_FEEDBACK_USE_STOCK && $conf->global->NOMENCLATURE_FEEDBACK_INIT_STOCK)){
        $nbCols ++;
        print '<td class="liste_titre" align="left">';
        if($editMode && !empty($conf->global->NOMENCLATURE_FEEDBACK_USE_STOCK)){
            print img_picto($langs->trans('ApplyPlanned'),'rightarrow', 'class="loadAllAllowed" ');
        }
        print $langs->trans('QtyAllowed').'</td>';
    }
    print '<td class="liste_titre" align="center">'.$langs->trans('QtyUsed').'</td>';
    
    if($conf->global->NOMENCLATURE_FEEDBACK_INIT_STOCK && !empty($conf->global->NOMENCLATURE_FEEDBACK_USE_STOCK) ){
        print '<td class="liste_titre" align="center">'.$langs->trans('QtyNotUsed').'</td>';
        $nbCols ++;
    }
    
    print '<td class="liste_titre" align="left">';
    if($editMode && !empty($conf->global->NOMENCLATURE_FEEDBACK_USE_STOCK))
    {
        print img_picto($langs->trans('ApplyPlanned'),'rightarrow', 'class="loadAllPlanned" ');
    }
    print $langs->trans('QtyNewConsume').'</td>';
    //print '		<td class="liste_titre" align="center">'.$langs->trans('QtyReturn').'</td>';
    
    
    if(!empty($conf->global->NOMENCLATURE_FEEDBACK_DISPLAY_RENTABILITY))
    {
        print '<td class="liste_titre" align="center">'.$langs->trans('AmountCost').'</td>';
        print '<td class="liste_titre" align="center">'.$langs->trans('AmountCostWithCharge').'</td>';
        print '<td class="liste_titre" align="center">'.$langs->trans('PV').'</td>';
    }
    
    print '	</tr>';
}


function saveFeedbackForm(){
    
    global $langs, $conf, $db, $user;
    
    $TQty = GETPOST('qtyConsume', 'array');
    $TStartQty = GETPOST('start-qty', 'array');
    $TStockAllowed = GETPOST('stockAllowed', 'array');
    $originType = GETPOST('origin', 'aZ09');
    $fk_origin = GETPOST('fk_origin', 'int');
    
    $countError = 0;
    $countSave  = 0;
    $countInit  = 0;
    
    if($conf->stock->enabled){
        dol_include_once('product/stock/class/mouvementstock.class.php');
    }
    
    $origin=false;
    if(!empty($originType) && !empty($fk_origin)){
        if($originType == 'commande'){
            $origin = new Commande($db);
            $origin->fetch($fk_origin);
        }
        elseif($originType == 'propal'){
            $origin = new Propal($db);
            $origin->fetch($fk_origin);
        }
    }
    
    
    if(!empty($TQty))
    {
        
        $PDOdb = new TPDOdb;
        foreach ( $TQty as $fk_nomenclature => $TProduct){
            
            foreach ( $TProduct as $fk_product => $qty){
                
                $qty = price2num($qty);
                
                $feedback = new TNomenclatureFeedback();
                
                $firstStockMovement = false;
                if(!$feedback->loadByProduct($PDOdb, $originType, $fk_origin, $fk_product, $fk_nomenclature)){
                    
                    $feedback->fk_nomenclature  = $fk_nomenclature;
                    $feedback->fk_product       = $fk_product;
                    $feedback->fk_origin        = $fk_origin;
                    $feedback->origin           = $originType;
                    $feedback->note             = '';
                    $firstStockMovement = true;
                }
                
                $feedback->qtyUsed = $feedback->qtyUsed + floatval($qty);
                
                // récupération de l'entrepot
                
                
                $fk_warehouse = GETPOST('entrepot-'.$fk_nomenclature.'-'.$fk_product, 'int');
                if(!empty($fk_warehouse)){
                    $feedback->fk_warehouse = $fk_warehouse;
                }
                
                
                if(!empty($conf->global->NOMENCLATURE_FEEDBACK_USE_STOCK) && !empty($conf->stock->enabled) && !empty($feedback->fk_warehouse)){
                    $mouvementStock = new MouvementStock($db);
                    
                    if(!empty($origin)){
                        $mouvementStock->origin = $origin;
                    }
                    
                    $label = $langs->trans('nomenclatureStockFeedback');
                    if($firstStockMovement ){
                        $label = $langs->trans('nomenclatureStockChantier');            
                    }
                    
                    
                    if(!empty($conf->global->NOMENCLATURE_FEEDBACK_INIT_STOCK) && isset($TStockAllowed[$fk_nomenclature][$fk_product])){
                        // Affectation du stock au chantier
                        // Modification des mouvements de stock
                        $qtyDelta = price2num($TStockAllowed[$fk_nomenclature][$fk_product]) - $feedback->stockAllowed;
                        $feedback->stockAllowed = price2num($TStockAllowed[$fk_nomenclature][$fk_product]) ;
                    }
                    else{
                        // Modification des mouvements de stock
                        $qtyDelta = $qty;
                        $feedback->stockAllowed = $qty;
                    }
                    
                    if(!empty($qtyDelta)){
                        
                        if(0 > $qtyDelta){
                            $mouvementStock->reception($user, $fk_product, $feedback->fk_warehouse, abs($qtyDelta), 0, $label);
                        }
                        else{
                            $mouvementStock->livraison($user, $fk_product, $feedback->fk_warehouse, abs($qtyDelta), 0, $label);
                        }
                    }
                    
                }
                
                
                
                
                
                
                
                if($feedback->save($PDOdb))
                {
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
    
    if($countInit>0){
        setEventMessage($langs->trans('nomenclatureStockInitChantier'));
    }
    
    
}

// getProductCache était plus parlant comme nom mais c'est un nom qui risque d'etre dejà chargé par une autre lib
function getProductNomenclatureCache($id){
    global $TProductCache,$db;
    
    if(empty($TProductCache[$id])){
        $TProductCache[$id]=new Product($db);
        if($TProductCache[$id]->fetch($id)<1){
            $TProductCache[$id]= false;
        }
    }
    
    return $TProductCache[$id];
}

// getEntrepotCache était plus parlant comme nom mais c'est un nom qui risque d'etre dejà chargé par une autre lib
function getEntrepotNomenclatureCache($id){
    global $TEntrepotCache,$db;
    
    if(empty($TEntrepotCache[$id])){
        $TEntrepotCache[$id]=new Entrepot($db);
        if($TEntrepotCache[$id]->fetch($id)<1){
            $TEntrepotCache[$id]= false;
        }
    }
    
    return $TEntrepotCache[$id];
}


