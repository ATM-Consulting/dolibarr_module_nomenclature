<?php

require 'config.php';
dol_include_once('/product/class/product.class.php');
dol_include_once('/fourn/class/fournisseur.product.class.php');
dol_include_once('/core/class/html.formother.class.php');
dol_include_once('/core/lib/product.lib.php');
dol_include_once('/nomenclature/class/nomenclature.class.php');
dol_include_once('/product/class/html.formproduct.class.php');
if($conf->workstation->enabled) {
    dol_include_once('/workstation/class/workstation.class.php');
}
    
$langs->load("stocks");

$product = new Product($db);
$product->fetch(GETPOST('fk_product'), GETPOST('ref'));

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
else if($action==='add_nomenclature') {
    
    $n=new TNomenclature;
    $n->set_values($_REQUEST);
    $n->save($PDOdb);
    
    
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
	</script><?php
	
	$TNomenclature = TNomenclature::get($PDOdb, $product->id);
	
	foreach($TNomenclature as $iN => &$n) {
	
	    _fiche_nomenclature($PDOdb, $n, $product, $product->id, 'product');
		        
	}
	
	
	?>
	<div class="tabsAction">
	<div class="inline-block divButAction"><a href="?action=add_nomenclature&fk_product=<?php echo $product->id ?>" class="butAction"><?php echo $langs->trans('AddNomenclature'); ?></a></div>
	</div>
	<?php
	
	dol_fiche_end();
	
	  
	
	llxFooter();
		
	
}
function _fiche_nomenclature(&$PDOdb, &$n,&$product, $fk_object=0, $object_type='product', $qty_ref=1) {
	global $langs, $conf, $db, $user;

	$form=new Form($db);
	
    $formCore=new TFormCore('auto', 'form_nom_'.$n->getId(), 'post', false);
    echo $formCore->hidden('action', 'save_nomenclature');
    echo $formCore->hidden('fk_nomenclature', $n->getId());
    echo $formCore->hidden('fk_product', $product->id);
    echo $formCore->hidden('fk_object', $fk_object);
    echo $formCore->hidden('object_type', $object_type);
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
                           } 
                           ?>
                       </tr>
                       <?php
                       $class='';$total_produit = $total_mo  = 0;
                       foreach($TNomenclatureDet as $k=>&$det) {
                           
                           $class = ($class == 'impair') ? 'pair' : 'impair';
                           
                           ?>
                           <tr class="<?php echo $class ?>">
                               <td><?php echo $formCore->combo('', 'TNomenclature['.$k.'][product_type]', TNomenclatureDet::$TType, $det->product_type) ?></td>
                               <td><?php 
                                    $p_nomdet = new Product($db);
                                    $p_nomdet->fetch($det->fk_product);
                                    
                                    echo $p_nomdet->getNomUrl(1).' '.$p_nomdet->label;
									if($p_nomdet->load_stock() < 0) $p_nomdet->load_virtual_stock();
									
									// On récupère le dernier tarif fournisseur pour ce produit
									$q = 'SELECT fk_availability FROM '.MAIN_DB_PREFIX.'product_fournisseur_price WHERE fk_product = '.$p_nomdet->id.' AND fk_availability > 0 ORDER BY rowid DESC LIMIT 1';
									$resql = $db->query($q);
									$res = $db->fetch_object($resql);
									if(!empty($conf->global->FOURN_PRODUCT_AVAILABILITY))
									{
										echo '<td>';
										if($res->fk_availability > 0) {
											$form->load_cache_availability();
											$availability=$form->cache_availability[$res->fk_availability]['label'];
											echo $availability;
											$availability='';
										}
										echo '</td>';
									}
									
                                    
                               ?></td>
                               <td>
                               	<?php echo $p_nomdet->stock_reel; ?>
                               </td>    
                               <td>
                               	<?php
                               		if($conf->asset->enabled){
                               			
                               			// On récupère les quantités dans les OF
                               			$q = 'SELECT ofl.qty, ofl.qty_needed, ofl.type 
                               					FROM '.MAIN_DB_PREFIX.'assetOf of 
                               					INNER JOIN '.MAIN_DB_PREFIX.'assetOf_line ofl ON(ofl.fk_assetOf = of.rowid) 
                               					WHERE fk_product = '.$p_nomdet->id.' AND of.status NOT IN("DRAFT","CLOSE")';
	                               		$resql = $db->query($q);
										
										// On régule le stock théorique en fonction de ces quantités
										while($res = $db->fetch_object($resql)) {
											if($res->type === 'TO_MAKE') $p_nomdet->stock_theorique += $res->qty; // Pour les TO_MAKE la bonne qté est dans le champ qty
											elseif($res->type === 'NEEDED') $p_nomdet->stock_theorique -= $res->qty_needed;
										}
										
									}
                               		echo $p_nomdet->stock_theorique; 
                               	?>
                               </td>    
                               <td><?php echo $formCore->texte('', 'TNomenclature['.$k.'][qty]', $det->qty, 7,100) ?></td>
                               
                               
                               <td><a href="<?php echo dol_buildpath('/nomenclature/nomenclature.php',1) ?>?action=delete_nomenclature_detail&k=<?php echo $k ?>&fk_nomenclature=<?php 
                               echo $n->getId() ?>&fk_product=<?php echo $product->id ?>&fk_object=<?php 
                               echo $fk_object ?>&object_type=<?php echo $object_type ?>&qty_ref=<?php 
                               echo $qty_ref ?>"><?php echo img_delete() ?></a></td>
                               
                               <?php
                               
	                            if($user->rights->nomenclature->showPrice) {
	                            	$price = $det->getSupplierPrice($PDOdb, $det->qty); 
                                    $total_produit+=$price;
									
									$coef = ( $det->product_type == 3) ? $conf->global->NOMENCLATURE_COEF_CONSOMMABLE : $conf->global->NOMENCLATURE_COEF_FOURNITURE;
									$total_produit_coef+=$price * $coef;
									
                                   
									echo '<td align="right">'; 
                                    echo price($price) ;
                                	echo '</td>'; 
									echo '<td align="right">'; 
                                    echo price(round($price * $coef,2)) ;
                                	echo '</td>'; 
	                            }
                               ?>                        
                           </tr>
                           <?
                           
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
                           <td><?php 
                                
                                echo $ws->workstation->getNomUrl(1);
                                
                           ?></td>    
                           <td><?php echo $formCore->texte('', 'TNomenclatureWorkstation['.$k.'][nb_hour_prepare]', $ws->nb_hour_prepare, 7,100) ?></td>
                           <td><?php echo $formCore->texte('', 'TNomenclatureWorkstation['.$k.'][nb_hour_manufacture]', $ws->nb_hour_manufacture, 7,100) ?></td>
                           <td><?php echo $ws->nb_hour ?></td>
                           <td><?php echo $formCore->texte('', 'TNomenclatureWorkstation['.$k.'][rang]', $ws->rang, 3,3) ?></td>
                           
                           <td><a href="<?php echo dol_buildpath('/nomenclature/nomenclature.php',1); ?>?action=delete_ws&k=<?php echo $k ?>&fk_product=<?php echo $product->id ?>&fk_nomenclature=<?php 
                           echo $n->getId() ?>&fk_object=<?php echo $fk_object ?>&object_type=<?php 
                           echo $object_type ?>&qty_ref=<?php echo $qty_ref ?>"><?php echo img_delete() ?></a></td>
                           <?php
                           
                           if($user->rights->nomenclature->showPrice) {		
                           
	                           echo '<td align="right">'; 
                               $price = ($ws->workstation->thm + $ws->workstation->thm_machine) * $ws->nb_hour; 
                               $total_mo+=$price;
                               echo price($price) ;
	                           echo '</td>';      
	                           
	                      }                   
                       ?></tr>
                       <?php
                       
                       
                   }
					if($user->rights->nomenclature->showPrice) {		
	                    ?><tr class="liste_total">
	                           <td><?php echo $langs->trans('Total'); ?></td>
	                           <td colspan="4">&nbsp;</td>
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
				$PR_coef = $total_mo+$total_produit_coef;
					
		        ?>     
		        <tr class="liste_total" >
                       <td style="font-weight: bolder;"><?php echo $langs->trans('AmountCostWithCharge'); ?></td>
                       <td colspan="3">&nbsp;</td>
                       <td style="font-weight: bolder; text-align: right;"><?php echo price(round($PR_coef,2)); ?></td>
		        </tr>
		        <tr class="liste_total" >
                       <td style="font-weight: bolder;"><?php echo $langs->trans('PriceConseil', $conf->global->NOMENCLATURE_COEF_MARGE); ?></td>
                       <td colspan="3">&nbsp;</td>
                       <td style="font-weight: bolder; text-align: right;"><?php echo price(round($PR_coef * (100 / (100 - $conf->global->NOMENCLATURE_COEF_MARGE)) ,2)); ?></td>
		        </tr>
		        <?php
		}
		
		?><tr>
            <td align="right" colspan="5">
                <div class="tabsAction">
                    <?php
                    
                    if($conf->workstation->enabled) {
                           
                           echo $formCore->combo('', 'fk_new_workstation', TWorkstation::getWorstations($PDOdb), -1);
                        ?>
                        <div class="inline-block divButAction">
                        <input type="submit" name="add_workstation" class="butAction" value="<?php echo $langs->trans('AddWorkstation'); ?>" />
                        </div>
                        <?
                    }
                    
                    ?>
                    
                    <?php
                        print $form->select_produits('', 'fk_new_product_'.$n->getId(), '', 0);
                    ?>
                   <div class="inline-block divButAction">
                    <input type="submit" name="add_nomenclature" class="butAction" value="<?php echo $langs->trans('AddProductNomenclature'); ?>" />
                   </div>
                   <div class="inline-block divButAction">
                    <input type="submit" name="save_nomenclature" class="butAction" value="<?php echo $langs->trans('SaveNomenclature'); ?>" />
                   </div>
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
