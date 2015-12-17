<?php

require 'config.php';
dol_include_once('/product/class/product.class.php');
dol_include_once('/fourn/class/fournisseur.product.class.php');
dol_include_once('/core/class/html.formother.class.php');
dol_include_once('/core/lib/product.lib.php');
dol_include_once('/nomenclature/class/nomenclature.class.php');
dol_include_once('/product/class/html.formproduct.class.php');
dol_include_once('/nomenclature/lib/nomenclature.lib.php');

if($conf->workstation->enabled) {
    dol_include_once('/workstation/class/workstation.class.php');
}
    
$langs->load("stocks");

$product = new Product($db);
$fk_product = GETPOST('fk_product', 'int');
$product_ref = GETPOST('ref', 'alpha');
if ($fk_product || $product_ref) $product->fetch($fk_product, $product_ref);

$action= GETPOST('action');

$PDOdb=new TPDOdb;

$fk_object=(int)GETPOST('fk_object');
$fk_nomenclature=(int)GETPOST('fk_nomenclature');
$object_type = GETPOST('object_type');

if(empty($object_type)) {
    $object_type='product';
    $fk_object = $product->id;
}

$qty_ref = (float)GETPOST('qty_ref');


if($action==='delete_nomenclature') {
    $n=new TNomenclature;
    $n->load($PDOdb, GETPOST('fk_nomenclature'));
    $n->delete($PDOdb);
    
    setEventMessage('NomenclatureDeleted');
    
}
else if($action==='clone_nomenclature') {
    
	cloneNomenclatureFromProduct($PDOdb, GETPOST('fk_product_clone', 'int'), $fk_object, $object_type);
}
else if($action==='add_nomenclature') {
    
    $n=new TNomenclature;
    $n->set_values($_REQUEST);
    $n->save($PDOdb);
    
    
}
else if($action==='add_fk_nomenclature') {
	//TODO ajouter les enfants de la nomenclature passé en post à la nomenclature courrante
	
}
else if($action === 'delete_nomenclature_detail') {
    
	$n=new TNomenclature;
	
    $n->load($PDOdb, $fk_nomenclature);
    
	$n->TNomenclatureDet[GETPOST('k')]->to_delete = true;
    
    $n->save($PDOdb);
    
}
else if($action === 'delete_ws') {
	$n=new TNomenclature;
    
    $n->load($PDOdb, $fk_nomenclature);
    $n->TNomenclatureWorkstation[GETPOST('k')]->to_delete = true;
    $n->save($PDOdb);
    
}
else if($action==='save_nomenclature') {
    
	if (GETPOST('apply_nomenclature_price'))
	{
		$price_buy = GETPOST('price_buy');
		$price_to_sell = GETPOST('price_to_sell');
		
		switch ($object_type) {
			case 'propal':
				dol_include_once('/comm/propal/class/propal.class.php');
				$n=new TNomenclature;
				$n->load($PDOdb, $fk_nomenclature);

				$propal = new Propal($db);
				$propal->fetch(GETPOST('fk_origin', 'int'));

				foreach ($propal->lines as $line)
				{
					if ($line->id == $fk_object)
					{
						$propal->updateline($fk_object, $price_to_sell, $line->qty, $line->remise_percent, $line->tva_tx, $line->localtax1_tx, $line->localtax2_tx, $line->desc, 'HT', $line->info_bits, $line->special_code, $line->fk_parent_line, $line->skip_update_total, $line->fk_fournprice, $price_buy, $line->product_label, $line->product_type, $line->date_start, $line->date_end, $line->array_options, $line->fk_unit);		
					}
				}
				
				break;
			case 'commande':
				dol_include_once('/commande/class/commande.class.php');
				$n=new TNomenclature;
				$n->load($PDOdb, $fk_nomenclature);
				
				$commande = new Commande($db);
				$commande->fetch(GETPOST('fk_origin', 'int'));
				
				foreach ($commande->lines as $line)
				{
					if ($line->id == $fk_object)
					{
						$commande->updateline($fk_object, $line->desc, $price_to_sell, $line->qty, $line->remise_percent, $line->tva_tx, $line->localtax1_tx, $line->localtax2_tx, 'HT', $line->info_bits, $line->date_start, $line->date_end, $line->product_type, $line->fk_parent_line, $line->skip_update_total, $line->fk_fournprice, $price_buy, $line->product_label, $line->special_code, $line->array_options, $line->fk_unit);		
					}
				}
				break;
		}
		
	}
	elseif (GETPOST('clone_nomenclature'))
	{
		$n=new TNomenclature;
		$n->load($PDOdb, $fk_nomenclature);
		$n->delete($PDOdb);
		
		cloneNomenclatureFromProduct($PDOdb, GETPOST('fk_clone_from_product'), $fk_object, $object_type);
	}
	else 
	{
		$n=new TNomenclature;
    
	    if($fk_nomenclature>0)$n->load($PDOdb, $fk_nomenclature);
	    else $n->loadByObjectId($PDOdb, $fk_object, $object_type,true, $product->id, $qty_ref);
	    
	    $n->set_values($_POST);
	    
	    $n->is_default = (int)GETPOST('is_default');
	    
		if($n->is_default>0) TNomenclature::resetDefaultNomenclature($PDOdb, $n->fk_product);
		
	    if(!empty($_POST['TNomenclature'])) {
	        foreach($_POST['TNomenclature'] as $k=>$TDetValues) {
	            $n->TNomenclatureDet[$k]->set_values($TDetValues);
	        }
	    }
	    
	    if(!empty($_POST['TNomenclatureWorkstation'])) {
	        foreach($_POST['TNomenclatureWorkstation'] as $k=>$TDetValues) {
	            $n->TNomenclatureWorkstation[$k]->set_values($TDetValues);
	        }
	    }
	    
	    $fk_new_product = (int)GETPOST('fk_new_product_'.$n->getId());
	    if(GETPOST('add_nomenclature') && $fk_new_product>0) {
	        $k = $n->addChild($PDOdb, 'TNomenclatureDet');
	        $det = &$n->TNomenclatureDet[$k];
	        $det->fk_product = $fk_new_product;
	    }
	    
	    $fk_new_workstation = GETPOST('fk_new_workstation');
	    if(GETPOST('add_workstation') && $fk_new_workstation>0 ) {
	        $k = $n->addChild($PDOdb, 'TNomenclatureWorkstation');
	        $det = &$n->TNomenclatureWorkstation[$k];
	        $det->fk_workstation = $fk_new_workstation;
	        $det->rang = $k+1; 
	    }
	    
		setEventMessage($langs->trans('NomenclatureSaved'));
	    
	    $n->save($PDOdb);
	}
	
}

if($object_type != 'product') {
	
    $langs->load('nomenclature@nomenclature');
    
	$n=new TNomenclature;
    $n->loadByObjectId($PDOdb,$fk_object, $object_type, false, $product->id, $qty_ref);
   
    _fiche_nomenclature($PDOdb, $n, $product, $fk_object, $object_type, $qty_ref);
}
else{
	_show_product_nomenclature($PDOdb, $product);	
}



$db->close();
function _show_product_nomenclature(&$PDOdb, &$product) {
	global $user, $langs, $db, $conf;
	
	llxHeader('','Nomenclature');

    $head=product_prepare_head($product, $user);
	$titre=$langs->trans('Nomenclature');
	$picto=($product->type==1?'service':'product');
	dol_fiche_head($head, 'nomenclature', $titre, 0, $picto);
	
	headerProduct($product);
	
	?><script type="text/javascript">
		function uncheckOther(obj)
		{
			$("input[name=is_default]").not($(obj)).prop("checked", false);	
		}
		function deleteNomenc(fk_nomenclature) {
		    
		    if(window.confirm('Vous-êtes sûr ?')) {
		        
		        document.location.href="?action=delete_nomenclature&fk_product=<?php echo $product->id; ?>&fk_nomenclature="+fk_nomenclature;
		        
		    }
		    
		    
		}
		$(document).ready(function() {
            $("input[name=clone_nomenclature]").click(function() {
                document.location.href="?action=clone_nomenclature&fk_product=<?php echo $product->id; ?>&fk_product_clone="+$("#fk_clone_from_product").val();
            });
		    
		});
		
	</script><?php
	
	$TNomenclature = TNomenclature::get($PDOdb, $product->id);
	
	foreach($TNomenclature as $iN => &$n) {
	
	    _fiche_nomenclature($PDOdb, $n, $product, $product->id, 'product');
		        
	}
	
	
	?>
	<div class="tabsAction">
		<div class="inline-block divButAction">
			<a href="?action=add_nomenclature&fk_product=<?php echo $product->id ?>&fk_object=<?php echo $product->id ?>" class="butAction"><?php echo $langs->trans('AddNomenclature'); ?></a>
		</div>
	
		<?php
		   //$form=new Form($db);
	       //print $form->select_produits('', 'fk_clone_from_product', '', 0);
			$htmlname = 'fk_clone_from_product';
			$urloption='htmlname='.$htmlname.'&outjson=1&price_level=0&type=&mode=1&status=1&finished=2';
			print ajax_autocompleter('', $htmlname, dol_buildpath('/nomenclature/ajax/products.php', 1), $urloption, $conf->global->PRODUIT_USE_SEARCH_TO_SELECT, 0, array());
			print $langs->trans("RefOrLabel").' : ';
			print '<input type="text" size="20" name="search_'.$htmlname.'" id="search_'.$htmlname.'" value="" autofocus />';
	    ?>
	    <div class="inline-block divButAction">
	        <input type="button" name="clone_nomenclature" class="butAction" value="<?php echo $langs->trans('CloneNomenclatureFromProduct'); ?>" />
	    </div>
	</div>
	<?php
	
	
	$liste = new TListviewTBS('listeUse');
	
	$sql="SELECT n.fk_object as 'Id Nomenclature', n.fk_object, nd.qty
		
	FROM ".MAIN_DB_PREFIX."nomenclaturedet nd 
		LEFT JOIN ".MAIN_DB_PREFIX."nomenclature n ON (n.rowid=nd.fk_nomenclature)
	WHERE nd.fk_product=".$product->id." AND n.object_type='product'";
	
	echo $liste->render($PDOdb, $sql, array(
		'limit'=>array(
			'nbLine'=>'30'
		)
		,'type'=>array(
			'qty'=>'number'
		)
		,'link'=>array(
			'Id Nomenclature'=>'<a href="'.dol_buildpath('/nomenclature/nomenclature.php?fk_product=@val@',1).'">'.img_picto($langs->trans('Nomenclature'),'object_list').' Nomenclature</a>'
		)
		,'liste'=>array(
			'titre'=>$langs->trans('ListUseNomenclaure')
			,'image'=>img_picto('','title.png', '', 0)
			,'picto_precedent'=>img_picto('','back.png', '', 0)
			,'picto_suivant'=>img_picto('','next.png', '', 0)
			,'messa geNothing'=>$langs->trans('NoUseInNomenclature')
			,'picto_search'=>img_picto('','search.png', '', 0)
		)
		,'title'=>array(
			'fk_object'=>'Produit'
			,'qty'=>'Quantité'
		)
		,'eval'=>array(
			'fk_object' => 'get_format_libelle_produit(@fk_object@)'
			
        )
		)
	);
	
	dol_fiche_end();
	
	  
	
	llxFooter();
		
	
}


function get_format_libelle_produit($fk_product = null) {
	global $db;

	if (!empty($fk_product)) {
		$product = new Product($db);
		$product->fetch($fk_product);
	
		$product->ref.=' '.$product->label;
	
		return  $product->getNomUrl(1);
	} else {
		return 'Produit non défini.';
	}
}

function _fiche_nomenclature(&$PDOdb, &$n,&$product, $fk_object=0, $object_type='product', $qty_ref=1) {
	global $langs, $conf, $db, $user;

	$json = GETPOST('json', 'int');
	$form=new Form($db);
	
    $formCore=new TFormCore('auto', 'form_nom_'.$n->getId(), 'post', false);
    echo $formCore->hidden('action', 'save_nomenclature');
	echo $formCore->hidden('json', $json);
    echo $formCore->hidden('fk_nomenclature', $n->getId());
    echo $formCore->hidden('fk_product', $product->id);
    echo $formCore->hidden('fk_object', $fk_object);
    echo $formCore->hidden('object_type', $object_type);
    echo $formCore->hidden('fk_origin', GETPOST('fk_origin', 'int'));
    echo $formCore->hidden('qty_ref', $qty_ref);
	
	?>
    <table class="liste" width="100%" id="nomenclature-<?php echo $n->getId(); ?>"><?php
    	if($object_type == 'product') {
	        ?><tr class="liste_titre">
	            <td class="liste_titre"><?php echo $langs->trans('Nomenclature').' n°'.$n->getId(); ?></td>
	            <td class="liste_titre"><?php echo $formCore->texte($langs->trans('Title'), 'title', $n->title, 50,255); ?></td>
	            <td class="liste_titre"><?php echo $formCore->texte($langs->trans('nomenclatureQtyReference'), 'qty_reference', $n->qty_reference, 5,10); ?></td>
	            <td align="right" class="liste_titre"><?php echo $formCore->checkbox('', 'is_default', array(1 => $langs->trans('nomenclatureIsDefault')), $n->is_default, 'onclick="javascript:uncheckOther(this);"') ?></td>
                <td align="right" class="liste_titre"><a href="javascript:deleteNomenc(<?php echo $n->getId(); ?>)"><?php echo img_delete($langs->trans('DeleteThisNomenclature')) ?></a></td>
	        </tr><?php
        }
        
        ?><tr>
           <td colspan="5">
               <?php
               
               $TNomenclatureDet = &$n->TNomenclatureDet;
               
               if(count($TNomenclatureDet>0)) {
                   
                   ?>
                   <table width="100%" class="liste">
                       <tr class="liste_titre">
                           <td class="liste_titre"><?php echo $langs->trans('Type'); ?></td>
                           <td class="liste_titre"><?php echo $langs->trans('Product'); ?></td>
                           <?php   
		                        if(!empty($conf->global->FOURN_PRODUCT_AVAILABILITY))
								{
									print '<td class="liste_titre">'.$langs->trans('Availability').'</td>';
								}
							?>
                           
                           <td class="liste_titre"><?php echo $langs->trans('PhysicalStock'); ?></td>
                           <td class="liste_titre"><?php echo $langs->trans('VirtualStock'); ?></td>
                           <td class="liste_titre"><?php echo $langs->trans('Qty'); ?></td>
                           <td class="liste_titre">&nbsp;</td>
                           <?php if($user->rights->nomenclature->showPrice) { 
                           		?><td class="liste_titre" align="right"><?php echo $langs->trans('AmountCost'); ?></td><?php 
                           		?><td class="liste_titre" align="right"><?php echo $langs->trans('AmountCostWithCharge'); ?></td><?php
                           		?><td class="liste_titre" align="right"><?php echo $langs->trans('AmountCostWithChargeCustom'); ?></td><?php
                           } 
                           ?>
                       </tr>
                       <?php
                       
                       switch ($object_type) 
                       {
                           case 'propal':
                               	dol_include_once('/comm/propal/class/propal.class.php');
                               	dol_include_once('/societe/class/societe.class.php');
								$object = new Propal($db);
					  		 	$object->fetch(GETPOST('fk_origin', 'int'));
								$object->fetch_thirdparty();
								$object_type_string = 'propal';
                               	break;
                           
                       }
					 	
					  
						//Chaque tableau de coef a pour key le rowid du coef
					   $TCoefStandard = TNomenclatureCoef::loadCoef($PDOdb);
					   $TCoefObject = TNomenclatureCoefObject::loadCoefObject($PDOdb, $object, $object_type_string);
					   
					   $total_charge = 0;
                       $class='';$total_produit = $total_mo  = 0;
                       foreach($TNomenclatureDet as $k=>&$det) {
                           
                           $class = ($class == 'impair') ? 'pair' : 'impair';
                           
                           ?>
                           <tr class="<?php echo $class ?>">
                               <td><?php echo $formCore->combo('', 'TNomenclature['.$k.'][code_type]', TNomenclatureDet::getTType($PDOdb), $det->code_type); ?></td>
                               <td><?php 
                                    $p_nomdet = new Product($db);
                                    if ($det->fk_product>0) 
                                    {
                                    	$p_nomdet->fetch($det->fk_product);
										echo $p_nomdet->getNomUrl(1).' '.$p_nomdet->label;
                                    
										if($p_nomdet->load_stock() < 0) $p_nomdet->load_virtual_stock(); // TODO AA pourquoi ? load_stock le fait et s'il échoue... :/
									}
									else 
									{
										echo '<input type="text" value="'.$det->title.'" name="TNomenclature['.$k.'][title]" />';
									}
									
									_draw_child_arbo($PDOdb, $p_nomdet->id, $det->qty);
									
                                ?></td><?php    
                                    
									if(!empty($conf->global->FOURN_PRODUCT_AVAILABILITY))
									{
										// On récupère le dernier tarif fournisseur pour ce produit
										if( $p_nomdet->id>0) {
											$q = 'SELECT fk_availability 
												FROM '.MAIN_DB_PREFIX.'product_fournisseur_price 
												WHERE fk_product = '.(int) $p_nomdet->id.' AND fk_availability > 0 ORDER BY rowid DESC LIMIT 1';
										
											$resql = $db->query($q);
									
											$res = $db->fetch_object($resql);
											$fk_availability = $res->fk_availability;
										}
										else {
											$fk_availability=0;
										}

										echo '<td rowspan="2">';
										if($fk_availability > 0) {
											$form->load_cache_availability();
											$availability=$form->cache_availability[$fk_availability]['label'];
											echo $availability;
											$availability='';
										}
										echo '</td>';
									}
									
                                    
                               ?>
                               <td rowspan="2">
                               	<?php echo $det->fk_product>0 ? $p_nomdet->stock_reel : '-'; ?>
                               </td>    
                               <td rowspan="2">
                               	<?php
                               		if($conf->asset->enabled && $p_nomdet->id>0){
                               			
                               			// On récupère les quantités dans les OF
                               			$q = 'SELECT ofl.qty, ofl.qty_needed, ofl.qty, ofl.type 
                               					FROM '.MAIN_DB_PREFIX.'assetOf of 
                               					INNER JOIN '.MAIN_DB_PREFIX.'assetOf_line ofl ON(ofl.fk_assetOf = of.rowid) 
                               					WHERE fk_product = '.$p_nomdet->id.' AND of.status NOT IN("DRAFT","CLOSE")';
	                               		$resql = $db->query($q);
										
										// On régule le stock théorique en fonction de ces quantités
										while($res = $db->fetch_object($resql)) {
											if($res->type === 'TO_MAKE') $p_nomdet->stock_theorique += $res->qty; // Pour les TO_MAKE la bonne qté est dans le champ qty
											elseif($res->type === 'NEEDED') $p_nomdet->stock_theorique -= empty($res->qty_needed) ? $res->qty : $res->qty_needed;
										}
													
									}
                               		echo !empty($det->fk_product) ? $p_nomdet->stock_theorique : '-'; 
                               	?>
                               </td>    
                               <td rowspan="2"><?php echo $formCore->texte('', 'TNomenclature['.$k.'][qty]', $det->qty, 7,100) ?></td>
                               
                               
                               <td rowspan="2"><a href="<?php echo dol_buildpath('/nomenclature/nomenclature.php',1) ?>?action=delete_nomenclature_detail&k=<?php echo $k ?>&fk_nomenclature=<?php 
                               echo $n->getId() ?>&fk_product=<?php echo $product->id ?>&fk_object=<?php 
                               echo $fk_object ?>&object_type=<?php echo $object_type ?>&qty_ref=<?php 
                               echo $qty_ref ?>&fk_origin=<?php echo GETPOST('fk_origin', 'int'); ?>&json=1"><?php echo img_delete() ?></a></td>
                               
                               <?php
                               
	                            if($user->rights->nomenclature->showPrice) {
	                            	$price = $det->getSupplierPrice($PDOdb, $det->qty,true); 
                                    
									if (!empty($TCoefObject[$det->code_type])) $coef = $TCoefObject[$det->code_type]->tx_object;
									elseif (!empty($TCoefStandard[$det->code_type])) $coef = $TCoefStandard[$det->code_type]->tx;
									else $coef = 1;
								
									$price_charge = $price * $coef;
									$price_final = ($det->price) ? $det->price : 0;
									
									$total_produit+=$price;
									$total_produit_coef+=$price_charge;
									$total_produit_coef_final+=$price_final;
									
									if ($price_final != 0) $total_charge += $price_final;
									else $total_charge += $price_charge; 
									
									echo '<td align="right"  rowspan="2">'; 
                                    echo price($price) ;
                                	echo '</td>'; 
									echo '<td align="right"  rowspan="2">'; 
                                    echo price(round($price_charge,2)) ;
                                	echo '</td>';
									echo '<td align="right"  rowspan="2">'; 
                                    echo '<input style="text-align:right;" name="TNomenclature['.$k.'][price]" value="'.price($price_final).'" size="5" />';
                                	echo '</td>'; 
	                            }
                               ?>                        
                           </tr>
                           <tr class="<?php echo $class; ?>">
                               <td colspan="2">
                                <?php
                                       echo $formCore->zonetexte('', 'TNomenclature['.$k.'][note_private]', $det->note_private, 80, 1);
                                ?>
                               </td>
                           </tr>
                           <?php
                           
                       }
						
				       if($user->rights->nomenclature->showPrice) {
				       		$colspan = 5;
							if($conf->global->FOURN_PRODUCT_AVAILABILITY > 0) $colspan += 1;		
                       ?>
                       <tr class="liste_total">
                           <td ><?php echo $langs->trans('Total'); ?></td>
                           <td colspan="<?php echo $colspan; ?>">&nbsp;</td>
                           <td align="right"><?php echo price(round($total_produit,2)); ?></td>
                           <td align="right"><?php echo price(round($total_produit_coef,2)); ?></td>
                           <td align="right"><?php echo price(round($total_produit_coef_final,2)); ?></td>
                          
                       </tr>
                       <?php
					   }
                       ?>
                   </table>
                   
                   <?php
                   
               }
               
               ?>
           </td> 
            
        </tr>
        <?php
       if($conf->workstation->enabled) {
           
       ?><tr>
           <td colspan="5"><?php
               ?>
               <table class="liste" width="100%">
               <tr class="liste_titre">
                   <td class="liste_titre"><?php echo $langs->trans('Type'); ?></td>
                   <td class="liste_titre"><?php echo $langs->trans('Worstations'); ?></td>
                   <td class="liste_titre"><?php echo $langs->trans('QtyPrepare'); ?></td>
                   <td class="liste_titre"><?php echo $langs->trans('QtyFabrication'); ?></td>
                   <td class="liste_titre"><?php echo $langs->trans('Qty'); ?></td>
                   <td class="liste_titre"><?php echo $langs->trans('Rank'); ?></td>
                   <td class="liste_titre">&nbsp;</td>
                 <?php if($user->rights->nomenclature->showPrice) {		
                 	?><td class="liste_titre" align="right"><?php echo $langs->trans('AmountCostWithCharge'); ?></td><?php } 
                 	
                 ?>
              
               </tr>
               <?php
                       
               $TNomenclatureWorkstation = &$n->TNomenclatureWorkstation;
                       
               if(!empty($TNomenclatureWorkstation)) {
                  
                   foreach($TNomenclatureWorkstation as $k=>&$ws) {
                       
                       $class = ($class == 'impair') ? 'pair' : 'impair';
                       
                       ?>
                       <tr class="<?php echo $class ?>">
                       		<td><?php echo $formCore->combo('', 'TNomenclatureWorkstation['.$k.'][code_type]', TNomenclatureDet::getTType($PDOdb), $ws->code_type); ?></td>
                           <td><?php 
                                
                                echo $ws->workstation->getNomUrl(1);
                                
                           ?></td>    
                           <td rowspan="2"><?php echo $formCore->texte('', 'TNomenclatureWorkstation['.$k.'][nb_hour_prepare]', $ws->nb_hour_prepare, 7,100) ?></td>
                           <td rowspan="2"><?php echo $formCore->texte('', 'TNomenclatureWorkstation['.$k.'][nb_hour_manufacture]', $ws->nb_hour_manufacture, 7,100) ?></td>
                           <td rowspan="2"><?php echo $ws->nb_hour ?></td>
                           <td rowspan="2"><?php echo $formCore->texte('', 'TNomenclatureWorkstation['.$k.'][rang]', $ws->rang, 3,3) ?></td>
                           
                           <td rowspan="2"><a href="<?php echo dol_buildpath('/nomenclature/nomenclature.php',1); ?>?action=delete_ws&k=<?php echo $k ?>&fk_product=<?php echo $product->id ?>&fk_nomenclature=<?php 
                           echo $n->getId() ?>&fk_object=<?php echo $fk_object ?>&object_type=<?php 
                           echo $object_type ?>&qty_ref=<?php echo $qty_ref ?>&fk_origin=<?php echo GETPOST('fk_origin', 'int'); ?>&json=<?php echo $json; ?>"><?php echo img_delete() ?></a></td>
                           <?php
                           
                           if($user->rights->nomenclature->showPrice) {
                           	
								$price = ($ws->workstation->thm + $ws->workstation->thm_machine) * $ws->nb_hour;
							   
								if (!empty($TCoefObject[$ws->code_type])) $coef = $TCoefObject[$ws->code_type]->tx_object;
								else $coef = 1;
								
								$price_charge = $price * $coef;
								$price_final = ($ws->price) ? $ws->price : $price_charge; //$ws->price = à la dernière colonne à droite pour le coût final (perso)
								
								$total_mo+=$price_charge;
						   
	                           echo '<td align="right" rowspan="2">';
                               echo price($price_charge) ;
	                           echo '</td>';
	                      }                   
                       ?>
                       </tr>
                       <tr class="<?php echo $class ?>">
                               <td colspan="2">
                                <?php
                                       echo $formCore->zonetexte('', 'TNomenclatureWorkstation['.$k.'][note_private]', $ws->note_private, 80, 1);
                                ?>
                               </td>
                       </tr>
                       
                       <?php
                       
                       
                   }
					if($user->rights->nomenclature->showPrice) {		
	                    ?><tr class="liste_total">
	                           <td><?php echo $langs->trans('Total'); ?></td>
	                           <td colspan="5">&nbsp;</td>
	                           <td>&nbsp;</td>
	                           <td align="right"><?php echo price($total_mo); ?></td>
	                          
	                    </tr><?php
					}
               }
               else{
                        
                   echo '<tr><td colspan="5">'. $langs->trans('WillUseProductWorkstationIfNotSpecified') .'</td></tr>';
               }     
           
                ?>          
               </table><?php
                            
                            
            ?></td>
        </tr><?php
        }  


		if($user->rights->nomenclature->showPrice) {
				$marge = TNomenclatureCoefObject::getMarge($PDOdb, $object, $object_type);
				$PR_coef = $total_mo+$total_charge;
				$price_buy = $total_mo+$total_produit_coef_final;
				$price_to_sell = price(round($PR_coef * (1 + ($marge->tx_object / 100)) ,2));
		        ?>     
		        <tr class="liste_total" >
                       <td style="font-weight: bolder;"><?php echo $langs->trans('AmountCostWithCharge'); ?></td>
                       <td colspan="3">&nbsp;</td>
                       <td style="font-weight: bolder; text-align: right;"><?php echo price(round($PR_coef,2)); ?></td>
                       	<?php echo $formCore->hidden('price_buy', price2num($price_buy)); ?>
		        </tr>
		        <tr class="liste_total" >
                       <td style="font-weight: bolder;"><?php echo $langs->trans('PriceConseil', $marge->tx_object); ?></td>
                       <td colspan="3">&nbsp;</td>
                       <td style="font-weight: bolder; text-align: right;">
                       	<?php echo $price_to_sell; ?>
                       	<?php echo $formCore->hidden('price_to_sell', price2num($price_to_sell)); ?>
                       </td>
		        </tr>
		        <?php
		}
		
		?><tr>
            <td align="right" colspan="5">
                <div class="tabsAction">
                    <?php
                    
                    if($conf->workstation->enabled) {
                           
                           echo $formCore->combo('', 'fk_new_workstation',TWorkstation::getWorstations($PDOdb, false, true), -1);
                        ?>
                        <div class="inline-block divButAction">
                        <input type="submit" name="add_workstation" class="butAction" value="<?php echo $langs->trans('AddWorkstation'); ?>" />
                        </div>
                        <?php
                    }
                    
                    ?>
                    <div>
	                    <?php
	                        print $form->select_produits('', 'fk_new_product_'.$n->getId(), '', 0);
	                    ?>
		                <div class="inline-block divButAction">
		                    <input type="submit" name="add_nomenclature" class="butAction" value="<?php echo $langs->trans('AddProductNomenclature'); ?>" />
		                </div>
		                
                   </div>
                   
                   <?php if ($json == 1) { ?>
                   		<style type="text/css">
                   			.dialogSouldBeZindexed {
                   				z-index:101 !important;
                   			}
                   		</style>
						<div>
							<?php
							   //$form=new Form($db);
							  //print $form->select_produits('', 'fk_clone_from_product', $sql, 0);*/
							  
							  $htmlname = 'fk_clone_from_product';
							  $urloption='htmlname='.$htmlname.'&outjson=1&price_level=0&type=&mode=1&status=1&finished=2';
							  print ajax_autocompleter('', $htmlname, dol_buildpath('/nomenclature/ajax/products.php', 1), $urloption, $conf->global->PRODUIT_USE_SEARCH_TO_SELECT, 0, array());
							  print $langs->trans("RefOrLabel").' : ';
							  print '<input type="text" size="20" name="search_'.$htmlname.'" id="search_'.$htmlname.'" value="" autofocus />';
							  
							?>
							<div class="inline-block divButAction">
								<input type="submit" name="clone_nomenclature" class="butAction" value="<?php echo $langs->trans('CloneNomenclatureFromProduct'); ?>" />
							</div>
						</div>
	                   
                   <?php } ?>
                   
                   <div class="inline-block divButAction">
	                   <input type="submit" name="save_nomenclature" class="butAction" value="<?php echo $langs->trans('SaveNomenclature'); ?>" />
	               </div>
	               
                   <?php if ($json) { ?>
                   <div>
                   		<div class="inline-block divButAction">
		                    <input type="submit" name="apply_nomenclature_price" class="butAction" value="<?php echo $langs->trans('ApplyNomenclaturePrice'); ?>" />
		                </div>
                   </div>
                   <?php } ?>
                   
                </div>
            </td>
        </tr>
    </table>
    <?php
	
    $formCore->end();
	
}

function headerProduct(&$object) {
   global $langs, $conf, $db; 
    
    $form = new Form($db);
        
    print '<table class="border" width="100%">';
    
    
    // Ref
    print '<tr>';
    print '<td width="15%">' . $langs->trans("Ref") . '</td><td colspan="2">';
    print $form->showrefnav($object, 'ref', '', 1, 'ref');
    print '</td>';
    print '</tr>';
    
    // Label
    print '<tr><td>' . $langs->trans("Label") . '</td><td>' . ($object->label ? $object->label : $object->libelle) . '</td>';
    
    $isphoto = $object->is_photo_available($conf->product->multidir_output [$object->entity]);
    
    $nblignes = 5;
    if ($isphoto) {
        // Photo
        print '<td valign="middle" align="center" width="30%" rowspan="' . $nblignes . '">';
        print $object->show_photos($conf->product->multidir_output [$object->entity], 1, 1, 0, 0, 0, 80);
        print '</td>';
    }
    
    print '</tr>';
    
    
    // Status (to sell)
    print '<tr><td>' . $langs->trans("Status") . ' (' . $langs->trans("Sell") . ')</td><td>';
    print $object->getLibStatut(2, 0);
    print '</td></tr>';
    
    print "</table>\n";
    
  echo '<br />';
        
   
       
        
    
}

function _draw_child_arbo(&$PDOdb, $id_product, $qty = 1, $level = 1) {
global $db;        
        
    $n = new TNomenclature;
    $n->loadByObjectId($PDOdb, $id_product, 'product', false);
    
    foreach($n->TNomenclatureDet as &$det) {
                    
        $p_child = new Product($db);
        if ($det->fk_product)
        {
        	$p_child->fetch($det->fk_product);
        
	        echo '<br />'.str_repeat('&nbsp;&nbsp;&nbsp;',$level).'L '.$p_child->getNomUrl(1).' '.$p_child->label.' x '.($det->qty * $qty).' ';
	        _draw_child_arbo($PDOdb, $p_child->id, $det->qty * $qty, $level+1 );
		}
		else 
		{
			echo '<br />'.str_repeat('&nbsp;&nbsp;&nbsp;',$level).'L '.$det->title.' x '.($det->qty * $qty).' ';
			_draw_child_arbo($PDOdb, $p_child->id, $det->qty * $qty, $level+1 );
		}
    }
    
}
