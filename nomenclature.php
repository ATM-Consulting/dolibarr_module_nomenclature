<?php

require 'config.php';
dol_include_once('/product/class/product.class.php');
dol_include_once('/fourn/class/fournisseur.product.class.php');
dol_include_once('/core/class/html.formother.class.php');
dol_include_once('/core/lib/product.lib.php');
dol_include_once('/nomenclature/class/nomenclature.class.php');
dol_include_once('/commande/class/commande.class.php');
dol_include_once('/comm/propal/class/propal.class.php');
dol_include_once('/product/class/html.formproduct.class.php');
dol_include_once('/nomenclature/lib/nomenclature.lib.php');

if($conf->workstation->enabled) {
    dol_include_once('/workstation/class/workstation.class.php');
}

$hookmanager->initHooks(array('nomenclaturecard'));

$langs->load("stocks");
$langs->load("nomenclature@nomenclature");

$product = new Product($db);
$fk_product = GETPOST('fk_product', 'int');
$product_ref = GETPOST('ref', 'alpha');
if ($fk_product || $product_ref) $product->fetch($fk_product, $product_ref);

$qty_ref = (float)GETPOST('qty_ref'); // il s'agit de la qty de la ligne de document, si vide alors il faudra utiliser qty_reference de la nomenclature

$action= GETPOST('action');

$PDOdb=new TPDOdb;

$fk_object=(int)GETPOST('fk_object');
$fk_nomenclature=(int)GETPOST('fk_nomenclature');
$object_type = GETPOST('object_type');
$fk_origin = GETPOST('fk_origin');

if(empty($object_type)) {
    $object_type='product';
    $fk_object = $product->id;
}

if(! empty($object_type)) {
	$class = ucfirst($object_type);
	$object = new $class($db);
	if(! empty($fk_origin)) $object->fetch($fk_origin);
}

/*
 * Actions
 */

$parameters = array(
		'object_type' => $object_type,
		'fk_object' => $fk_object,
		'fk_nomenclature' => $fk_nomenclature,
		
);
$reshook = $hookmanager->executeHooks('doActions', $parameters, $product, $action); // Note that $action and $object may have been modified by some hooks
if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
if (empty($reshook))
{
	
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
	
	    if($n->getId()>0) {
	
	    $n->TNomenclatureDet[GETPOST('k')]->to_delete = true;
	
	    $n->save($PDOdb);
	   }
	}
	else if($action === 'delete_ws') {
	    $n=new TNomenclature;
	 //   $PDOdb->debug = true;
	    $n->load($PDOdb, $fk_nomenclature);
	
	    if($n->getId()>0) {
	    	$k = (int)GETPOST('k');
	//var_dump( $fk_nomenclature,$k,$n->TNomenclatureWorkstation);
		$n->TNomenclatureWorkstation[$k]->to_delete = true;
		$n->save($PDOdb);
	    }
	}
	else if($action==='save_nomenclature') {
		
		if (GETPOST('clone_nomenclature'))
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
		    else $n->loadByObjectId($PDOdb, $fk_object, $object_type,true, $product->id, $qty_ref, $fk_origin); // si pas de fk_nomenclature, alors on provient d'un document, donc $qty_ref tjr passé en param
	
			if(!$n->iExist && GETPOST('type_object')!='product') { // cas où on sauvegarde depuis une ligne et qu'il faut dupliquer la nomenclature
				$n->reinit();
			}
	
			$n->set_values($_POST);
	
		    $n->is_default = (int)GETPOST('is_default');
	
			if($n->is_default>0) TNomenclature::resetDefaultNomenclature($PDOdb, $n->fk_product);
	
		    if(!empty($_POST['TNomenclature'])) {
		    	// Réorganisation des clefs du tableau au cas où l'odre a été changé par déplacement des lignes
				$tab = array();
				foreach($_POST['TNomenclature'] as $val) $tab[] = $val;
				
		        foreach($tab as $k=>$TDetValues) {
		            $n->TNomenclatureDet[$k]->set_values($TDetValues);
		            
		            if(isset($_POST['TNomenclature_'.$k.'_workstations'])) {
		            	$n->TNomenclatureDet[$k]->workstations = implode(',', $_POST['TNomenclature_'.$k.'_workstations']);
		            }
		            
		        }
		    }
	
		    if(!empty($_POST['TNomenclatureWorkstation'])) {
		        foreach($_POST['TNomenclatureWorkstation'] as $k=>$TDetValues) {
		            $n->TNomenclatureWorkstation[$k]->set_values($TDetValues);
		        }
		    }
	
		    $fk_new_product = (int)GETPOST('fk_new_product_'.$n->getId());
		    if(GETPOST('add_nomenclature') && $fk_new_product>0) {
		    	if(!$n->addProduct($PDOdb, $fk_new_product)) {
					$p_err= new Product($db);
					$p_err->fetch($fk_new_product);
	
					setEventMessage($langs->trans('ThisProductCreateAnInfinitLoop').' '.$p_err->getNomUrl(0),'errors');
		    	}
	
		    }
	
		    $fk_new_workstation = GETPOST('fk_new_workstation');
		    if(GETPOST('add_workstation') && $fk_new_workstation>0 ) {
		        $k = $n->addChild($PDOdb, 'TNomenclatureWorkstation');
		        $det = &$n->TNomenclatureWorkstation[$k];
		        $det->fk_workstation = $fk_new_workstation;
		        $det->rang = $k+1;
		    }
	
			setEventMessage($langs->trans('NomenclatureSaved'));
			
			$n->setPrice($PDOdb,$qty_ref,$n->fk_object,$n->object_type, $fk_origin);

		    $n->save($PDOdb);
			
			// Fait l'update du PA et PU de la ligne si nécessaire
			_updateObjectLine($n, $object_type, $fk_object, GETPOST('fk_origin'), GETPOST('apply_nomenclature_price'));
			
		}
	
	}
}

if($object_type != 'product') {

    $langs->load('nomenclature@nomenclature');

    $n=new TNomenclature;
    $n->loadByObjectId($PDOdb,$fk_object, $object_type, false, $product->id, $qty_ref, GETPOST('fk_origin'));
    _fiche_nomenclature($PDOdb, $n, $product, $object, $fk_object, $object_type, $qty_ref);

}
else{
	_show_product_nomenclature($PDOdb, $product, $object);
}

$db->close();
function _show_product_nomenclature(&$PDOdb, &$product, &$object) {
	global $user, $langs, $db, $conf;

	llxHeader('',$langs->trans('Nomenclature'));

    $head=product_prepare_head($product, $user);
	$titre=$langs->trans('Nomenclature');
	$picto=($product->type==1?'service':'product');
	dol_fiche_head($head, 'nomenclature', $titre, 0, $picto);

	if ((float) DOL_VERSION >= 4.0) dol_banner_tab($product, 'ref', '', ($user->societe_id?0:1), 'ref');
	else headerProduct($product);
	
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
		echo '<div class="tabBar">';
		// On passe par là depuis l'onglet "Ouvrage" d'un produit, du coup il faut passer la qty_reference de la nomenclature
	    _fiche_nomenclature($PDOdb, $n, $product, $object, $product->id, 'product', $n->qty_reference);
		echo '</div>';
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
	        <input id="nomenclature_bt_clone_nomenclature" type="button" name="clone_nomenclature" class="butAction" value="<?php echo $langs->trans('CloneNomenclatureFromProduct'); ?>" />
	    </div>
	</div>
	<?php


	$liste = new TListviewTBS('listeUse');

	$sql="SELECT n.fk_object as 'Id', n.fk_object, nd.qty

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
			'Id'=>'<a href="'.dol_buildpath('/nomenclature/nomenclature.php?fk_product=@val@',1).'">'.img_picto($langs->trans('Nomenclature'),'object_list').' '.$langs->trans('Nomenclature').'</a>'
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
			'fk_object'=>$langs->trans('product')
			,'qty'=>$langs->trans('Qty')
			,'Id'=>$langs->trans('Nomenclature')
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

function _fiche_nomenclature(&$PDOdb, &$n,&$product, &$object, $fk_object=0, $object_type='product', $qty_ref=1) {
	global $langs, $conf, $db, $user, $hookmanager;

	$coef_qty_price = $n->setPrice($PDOdb,$qty_ref,$fk_object,$object_type,GETPOST('fk_origin'));

	$json = GETPOST('json', 'int');
	$form=new Form($db);

	if($n->getId() == 0 &&  count($n->TNomenclatureDet)+count($n->TNomenclatureWorkstation)>0) {
		echo '<div class="error">'.$langs->trans('NonLocalNomenclature').'</div>';
	}

    $formCore=new TFormCore('auto', 'form_nom_'.$n->getId(), 'post', false);
    echo $formCore->hidden('action', 'save_nomenclature');
    echo $formCore->hidden('json', $json);
    echo $formCore->hidden('fk_nomenclature', $n->getId());
    echo $formCore->hidden('fk_product', $product->id);
    echo $formCore->hidden('fk_object', $fk_object);
    echo $formCore->hidden('object_type', $object_type);
    echo $formCore->hidden('fk_origin', GETPOST('fk_origin', 'int'));
    echo $formCore->hidden('qty_ref', $qty_ref);
    echo $formCore->hidden('qty_price', $qty_price);

    $TCoef = TNomenclatureCoef::loadCoef($PDOdb);
    
	?>
	<script type="text/javascript">
	$(document).ready(function() {
		$("#det-table>tbody").sortable({
			handle:".handler"
			,placeholder: "ui-state-highlight"
			,stop:function(event,ui) {
				var sorted = $("#det-table>tbody").sortable( "toArray", { attribute: "rowid" } );

				$.ajax({
					url:"<?php echo dol_buildpath('/nomenclature/script/interface.php',1) ?>"
					,data:{
						put:"rang"
						,type:'det'
						,TRank:sorted
					}
				});

			}
		});
		$("#workstation-table>tbody").sortable({
			handle:".handler"
			,placeholder: "ui-state-highlight"
			,stop:function(event,ui) {
				var sorted = $("#workstation-table>tbody").sortable( "toArray", { attribute: "rowid" } );

				$.ajax({
					url:"<?php echo dol_buildpath('/nomenclature/script/interface.php',1) ?>"
					,data:{
						put:"rang"
						,type:'ws'
						,TRank:sorted
					}
				});

			}
		});

		<?php if(!empty($conf->global->NOMENCLATURE_ALLOW_USE_MANUAL_COEF)) { ?>
			// Récupération des coef
			TCoef = {<?php foreach($TCoef as $obj_coef) echo '"'.$obj_coef->code_type.'":'.$obj_coef->tx.','; ?>};
			
			$('.select_coef').change(function() {
				$(this).parent('td').find('input[type="number"]').val(TCoef[$(this).val()]);
			});
		<?php } ?>
		
	});
	</script>
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
                   <table width="100%" class="liste"  id="det-table">
                       <thead>
                       <tr class="liste_titre">
                           <th class="liste_titre col_type" width="5%"><?php echo $langs->trans('Type'); ?></th>
                           <th class="liste_titre col_product" width="55%"><?php echo $langs->trans('Product'); ?></th>
                           <?php
		                        if(!empty($conf->global->FOURN_PRODUCT_AVAILABILITY))
								{
									print '<th class="liste_titre" width="5%">'.$langs->trans('Availability').'</th>';
								}
						   
						   		if(!empty($conf->stock->enabled)) {
								   ?>
		
		                           <th class="liste_titre col_physicalStock" width="5%"><?php echo $langs->trans('PhysicalStock'); ?></th>
		                           <th class="liste_titre col_virtualStock" width="5%"><?php echo $langs->trans('VirtualStock'); ?></th>
		                        <?php } ?>
                           <th class="liste_titre col_qty" width="5%"><?php echo $langs->trans('Qty'); ?></th>
                           <?php if($user->rights->nomenclature->showPrice) {
                           		?><th class="liste_titre col_amountCost" align="right" width="5%"><?php echo $langs->trans('AmountCost'); ?></th><?php
                           		?><th class="liste_titre col_amountCostWithCharge" align="right" width="5%"><?php echo $langs->trans('AmountCostWithCharge'); ?></th><?php
                           		?><th class="liste_titre col_amountCostWithChargeCustom" align="right" width="5%"><?php echo $langs->trans('AmountCostWithChargeCustom'); ?></th><?php
                           }
                           ?>
                           <th class="liste_titre" width="1%">&nbsp;</th>
                           <th class="liste_titre" width="1%">&nbsp;</th>
                       </tr>
                       </thead>
                       <tbody>
                       <?php


						//Chaque tableau de coef a pour key le rowid du coef

					   $total_charge = 0;
                       $class='';$total_produit = $total_mo  = 0;
                       foreach($TNomenclatureDet as $k=>&$det) {

                           $class = ($class == 'impair') ? 'pair' : 'impair';
                           
                           ?>
                           <tr class="<?php echo $class ?>" rowid="<?php echo $det->getId(); ?>">
                               <td nowrap><?php echo $formCore->combo('', 'TNomenclature['.$k.'][code_type]', TNomenclatureDet::getTType($PDOdb), $det->code_type, 1, '', '', 'select_coef'); ?>
                               
                               <?php if(!empty($conf->global->NOMENCLATURE_ALLOW_USE_MANUAL_COEF)) { ?>
                               	&nbsp;<input type="number" step="0.1" name="TNomenclature[<?php echo $k; ?>][tx_custom]" style="width:50px;" value="<?php echo empty($det->tx_custom) ? $TCoef[$det->code_type]->tx : $det->tx_custom; ?>"/>
                               <?php } ?>
                               
                               </td><td><?php
                                    $p_nomdet = new Product($db);
                                    if ($det->fk_product>0 && $p_nomdet->fetch($det->fk_product)>0)
                                    {
										echo $p_nomdet->getNomUrl(1).' '.$p_nomdet->label;

										if($p_nomdet->load_stock() < 0) $p_nomdet->load_virtual_stock(); // TODO AA pourquoi ? load_stock le fait et s'il échoue... :/
	          		    }
        			    else
				   {
						echo '<input type="text" value="'.$det->title.'" name="TNomenclature['.$k.'][title]" />';
				  }

				   _draw_child_arbo($PDOdb, $p_nomdet->id, $det->qty);

									echo $formCore->zonetexte('', 'TNomenclature['.$k.'][note_private]', $det->note_private, 80, 1,' style="width:95%;"');

									if(!empty($conf->global->NOMENCLATURE_ALLOW_TO_LINK_PRODUCT_TO_WORKSTATION)) {
									
										if(empty($TWorkstationToSelect)) {
											$TWorkstationToSelect=array();
											foreach($n->TNomenclatureWorkstation as &$wsn) {
												$TWorkstationToSelect[$wsn->workstation->id] = $wsn->workstation->name;
											}
										}
										
										echo $form->multiselectarray('TNomenclature_'.$k.'_workstations', $TWorkstationToSelect,(empty($det->workstations) ? array() : explode(',', $det->workstations)),0,0,'minwidth300'  );
										
									}
									
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

										echo '<td>';
										if($fk_availability > 0) {
											$form->load_cache_availability();
											$availability=$form->cache_availability[$fk_availability]['label'];
											echo $availability;
											$availability='';
										}
										echo '</td>';
									}

								
								if(!empty($conf->stock->enabled)) {
	                               ?>
	                               <td>
	                               	<?php echo $det->fk_product>0 ? price($p_nomdet->stock_reel,'',0,1,1,2) : '-'; ?>
	                               </td>
	                               <td>
	                               	<?php
	                               		if($conf->of->enabled && $p_nomdet->id>0){
	
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
	                               		echo !empty($det->fk_product) ? price($p_nomdet->stock_theorique,'',0,1,1,2) : '-';
	                               	?>
	                               </td>
	                             <?php } ?>
                               <td class="ligne_col_qty"><?php
                               		echo $formCore->texte('', 'TNomenclature['.$k.'][qty]', $det->qty, 7,100);
							   		if($coef_qty_price != 1) echo '<br /> x '.price($coef_qty_price,'','',2,2) ;
							    ?></td>

                               <?php

	                            if($user->rights->nomenclature->showPrice) {

	                            	$price = price2num($det->calculate_price,'MT');
									$price_charge = price2num($det->charged_price,'MT');

									echo '<td align="right" valign="middle">';
									if(!empty($conf->global->NOMENCLATURE_ACTIVATE_DETAILS_COSTS)) {
										echo price( $price ).img_help(1,$langs->trans('PricePA'));
										echo '<span class="pricePMP"><br />'.price(price2num($det->calculate_price_pmp,'MT')).img_help(1,$langs->trans('PricePMP')).'</span>';
										if(!empty($conf->of->enabled)) echo '<span class="priceOF"><br />'.price(price2num($det->calculate_price_of,'MT')).img_help(1,$langs->trans('PriceOF')).'</span>';
									}
									else{
										echo price($price);
									}


                                	echo '</td>';
									echo '<td align="right" valign="middle">';
									if(!empty($conf->global->NOMENCLATURE_ACTIVATE_DETAILS_COSTS)) {
										echo price($price_charge);
										echo '<span class="pricePMP"><br />'.price(price2num($det->charged_price_pmp,'MT')).'</span>';
										if(!empty($conf->of->enabled)) echo '<span class="priceOF"><br />'.price(price2num($det->charged_price_of,'MT')).'</span>';
									}
									else{
                                    	echo price($price_charge);
									}
                                	echo '</td>';
									echo '<td align="right" valign="middle">';
                                    echo '<input style="text-align:right;" name="TNomenclature['.$k.'][price]" value="'.price($det->price).'" size="5" />';
                                	echo '</td>';
	                            }
                               ?>
                               
								<td><?php
								if($n->getId()>0)
								{
									$param = '?action=delete_nomenclature_detail&k='.$k.'&fk_nomenclature='.$n->getId().'&fk_product='.$product->id.'&fk_object='.$fk_object;
									$param.= '&object_type='.$object_type.'&qty_ref='.$qty_ref.'&fk_origin='.GETPOST('fk_origin', 'int').'&json='.$json;
								
									// Si la nomenclature a été enregistré puis que les lignes ont été delete, alors l'icone de suppression ne doit pas s'afficher car ce sont les lignes chargé depuis le load_original()
									if (! empty($n->iExist)) echo '<a href="'.dol_buildpath('/nomenclature/nomenclature.php',1).$param.'" class="tojs">'.img_delete().'</a>';
								}
								?></td>
                               
                               
                               <td align="center" class="linecolmove tdlineupdown"><?php $coldisplay++; ?>
									<a class="lineupdown handler" href="<?php echo $_SERVER["PHP_SELF"].'?fk_product='.$product->id.'&amp;action=up&amp;rowid='.$line->id; ?>">
									<?php echo img_picto('Move','grip'); ?>
									</a>
								</td>
                           </tr>
                           <?php

                       }

					?></tbody><tfoot><?php

				       if($user->rights->nomenclature->showPrice) {
				       		$colspan = 4;
							if($conf->global->FOURN_PRODUCT_AVAILABILITY > 0) $colspan += 1;
							if(empty($conf->stock->enabled)) $colspan -= 2;
                       ?>
                       <tr class="liste_total">
                           <td ><?php echo $langs->trans('Total'); ?></td>
                           <td class="total_colspan" colspan="<?php echo $colspan; ?>">&nbsp;</td>
                           <td align="right"><?php echo price(price2num($n->totalPR,'MT')); ?></td>
                           <td align="right"><?php echo price(price2num($n->totalPRC,'MT')); ?></td>
                           <td align="right"><?php /*echo price(round($total_produit_coef_final,2));*/ ?></td>
                           <td align="right"></td>
                           <td align="right"></td>
                       </tr>
                       <?php

                       if(!empty($conf->global->NOMENCLATURE_ACTIVATE_DETAILS_COSTS)) {
	                        ?>
	                       <tr class="liste_total">
	                           <td ><?php echo $langs->trans('TotalPricePMP'); ?></td>
	                           <td colspan="<?php echo $colspan; ?>">&nbsp;</td>
	                           <td align="right"><?php echo price(price2num($n->totalPR_PMP,'MT')); ?></td>
	                           <td align="right"><?php echo price(price2num($n->totalPRC_PMP,'MT')); ?></td>
	                           <td align="right"><?php /*echo price(round($total_produit_coef_final,2));*/ ?></td>
	                           <td align="right"></td>
	                           <td align="right"></td>

	                       </tr><?php

	                       if(!empty($conf->of->enabled)) {

		                       ?><tr class="liste_total">
		                           <td ><?php echo $langs->trans('TotalPriceOF'); ?></td>
		                           <td colspan="<?php echo $colspan; ?>">&nbsp;</td>
		                           <td align="right"><?php echo price(price2num($n->totalPR_OF,'MT')); ?></td>
		                           <td align="right"><?php echo price(price2num($n->totalPRC_OF,'MT')); ?></td>
		                           <td align="right"><?php /*echo price(round($total_produit_coef_final,2));*/ ?></td>
		                           <td align="right"></td>
		                           <td align="right"></td>

		                       </tr>
		                       <?php
						   }

					   }

					   }
                       ?>
                       </tfoot>
                   </table>

                   <?php

               }

               ?>
           </td>

        </tr>
        
        <tr>
			<td colspan="5">
				<div class="tabsAction">
					<div>
						<?php
						if(!empty($conf->global->NOMENCLATURE_ALLOW_JUST_MP)) {
							print $form->select_produits('', 'fk_new_product_'.$n->getId(), '', 0,0,-1,0);
						}
						else{
							print $form->select_produits('', 'fk_new_product_'.$n->getId(), '', 0,0,-1,2);
						}
						?>
						
						<div class="inline-block divButAction">
							<input id="nomenclature_bt_add_product" type="submit" name="add_nomenclature" class="butAction" value="<?php echo $langs->trans('AddProductNomenclature'); ?>" />
						</div>
					</div>
				</div>
			</td>
        </tr>
        
        
        <?php
       if($conf->workstation->enabled) {

       ?><tr>
           <td colspan="5"><?php
               ?>
               <table class="liste" width="100%" id="workstation-table">
               	<thead>
               <tr class="liste_titre">
                   <!--<th class="liste_titre"><?php echo $langs->trans('Type'); ?></th>-->
                   <th class="liste_titre" colspan="1" width="55%"><?php echo $langs->trans('Worstations'); ?></th>
                   <th class="liste_titre" colspan="1" width="5%"></th>
                   <?php if (!empty($conf->global->NOMENCLATURE_USE_TIME_BEFORE_LAUNCH)) {?>
                   <th class="liste_titre" width="5%"><?php echo $langs->trans('nb_days_before_beginning'); ?></th>
                   <?php }?>
                   <?php if (!empty($conf->global->NOMENCLATURE_USE_TIME_PREPARE)) {?>
                   <th class="liste_titre" width="5%"><?php echo $langs->trans('QtyPrepare'); ?></th>
                   <?php }?>
                   <?php if (!empty($conf->global->NOMENCLATURE_USE_TIME_DOING)) {?>
                   <th class="liste_titre" width="5%"><?php echo $langs->trans('QtyFabrication'); ?></th>
                   <?php }?>
                   <th class="liste_titre" width="5%"><?php echo $langs->trans('Qty'); ?></th>
                 <?php if($user->rights->nomenclature->showPrice) {
                 	?><th class="liste_titre" align="right" width="5%"><?php echo $langs->trans('AmountCostWithCharge'); ?></th><?php }

                 ?>
                 <th class="liste_titre" width="5%">&nbsp;</th>
                 <th class="liste_titre" width="1%">&nbsp;</th>
                 <th class="liste_titre" width="1%">&nbsp;</th>
               </tr>
               </thead>
               <tbody>
               <?php

               $TNomenclatureWorkstation = &$n->TNomenclatureWorkstation;

               if(!empty($TNomenclatureWorkstation)) {

                   foreach($TNomenclatureWorkstation as $k=>&$ws) {

                       $class = ($class == 'impair') ? 'pair' : 'impair';
                       /*
					    * <!-- Pas sur la MO	<td><?php
                       		echo $formCore->combo('', 'TNomenclatureWorkstation['.$k.'][code_type]', TNomenclatureDet::getTType($PDOdb), $ws->code_type);
                       	?></td> -->
                        */
                       ?>
                       <tr class="<?php echo $class ?>" rowid="<?php echo $ws->getId(); ?>">
                       	   <td colspan="1"><?php

                                echo $ws->workstation->getNomUrl(1);
                                echo $formCore->zonetexte('', 'TNomenclatureWorkstation['.$k.'][note_private]', $ws->note_private, 80, 1, ' style="width:95%;"');
                           ?></td>
                           <td>&nbsp;</td>
                           <?php if (!empty($conf->global->NOMENCLATURE_USE_TIME_BEFORE_LAUNCH)) {?>
                           <td ><?php echo $formCore->texte('', 'TNomenclatureWorkstation['.$k.'][nb_days_before_beginning]', $ws->nb_days_before_beginning, 7,100) ?></td>
                           <?php }?>
                           <?php if (!empty($conf->global->NOMENCLATURE_USE_TIME_PREPARE)) {?>
                           <td ><?php echo $formCore->texte('', 'TNomenclatureWorkstation['.$k.'][nb_hour_prepare]', $ws->nb_hour_prepare, 7,100) ?></td>
                           <?php }?>
                           <?php if (!empty($conf->global->NOMENCLATURE_USE_TIME_DOING)) {?>
                           <td ><?php
                           		echo $formCore->texte('', 'TNomenclatureWorkstation['.$k.'][nb_hour_manufacture]', $ws->nb_hour_manufacture, 7,100);
                           		if($coef_qty_price != 1) echo '<br /> x '.price($coef_qty_price,'','',2,2) ;
                           	?></td>
                           <?php }?>
                           <td ><?php
                           		echo $ws->nb_hour_calculate.'h';
						   ?></td>

                           <?php

                           if($user->rights->nomenclature->showPrice) {

								$price_charge = ($ws->price) ? $ws->price : $ws->calculate_price; //$ws->price = à la dernière colonne à droite pour le coût final (perso)
								$total_mo+=$price_charge;

	                           echo '<td align="right" valign="middle">';
                               echo price($price_charge) ;

							   if(!empty($conf->global->NOMENCLATURE_ACTIVATE_DETAILS_COSTS) && !empty($conf->of->enabled)) {
							   		  echo '<span class="priceOF"><br />'.price($ws->calculate_price_of,'','',1,1,2).img_help(1, $langs->trans('priceMO_OF')).'</span>' ;
							   }

	                           echo '</td>';
	                      }
                       ?>
						<td>&nbsp;</td>
						<td align="center">
							<?php
							if($n->getId()>0)
							{
								$param = '?action=delete_ws&k='.$k.'&fk_nomenclature='.$n->getId().'&fk_product='.$product->id.'&fk_object='.$fk_object;
								$param.= '&object_type='.$object_type.'&qty_ref='.$qty_ref.'&fk_origin='.GETPOST('fk_origin', 'int').'&json='.$json;

								// Si la nomenclature a été enregistré puis que les lignes ont été delete, alors l'icone de suppression ne doit pas s'afficher car ce sont les lignes chargé depuis le load_original()
								if (! empty($n->iExist)) echo '<a href="'.dol_buildpath('/nomenclature/nomenclature.php',1).$param.'" class="tojs">'.img_delete().'</a>';
							}
							?>
						</td>
						
                               <td align="center" class="linecolmove tdlineupdown"><?php $coldisplay++; ?>
									<a class="lineupdown handler" href="<?php echo $_SERVER["PHP_SELF"].'?fk_product='.$product->id.'&amp;action=up&amp;rowid='.$line->id; ?>">
									<?php echo img_picto('Move','grip'); ?>
									</a>
								</td>
                       </tr>

                       <?php


                   }

				?></tbody><tfoot><?php

					$colspan = 4;
					if (empty($conf->global->NOMENCLATURE_USE_TIME_BEFORE_LAUNCH)) $colspan--;
					if (empty($conf->global->NOMENCLATURE_USE_TIME_PREPARE)) $colspan--;
					if (empty($conf->global->NOMENCLATURE_USE_TIME_DOING)) $colspan--;
					
					if($user->rights->nomenclature->showPrice) {
	                    ?><tr class="liste_total">
	                           <td colspan="2"><?php echo $langs->trans('Total'); ?></td>
	                           <td colspan="<?php echo $colspan; ?>">&nbsp;</td>
	                           <td align="right"><?php echo price($n->totalMO); ?></td>
								<td>&nbsp;</td>
								<td></td>
								<td></td>
	                    </tr><?php
	                     if(!empty($conf->global->NOMENCLATURE_ACTIVATE_DETAILS_COSTS) && !empty($conf->of->enabled)) {
		                    ?><tr class="liste_total">
		                           <td colspan="2"><?php echo $langs->trans('TotalMO_OF'); ?></td>
		                           <td colspan="<?php echo $colspan; ?>">&nbsp;</td>
		                           <td align="right"><?php echo price($n->totalMO_OF,'','',1,1,2); ?></td>
		                           <td>&nbsp;</td>
									<td></td>
		                    </tr><?php
						 }
					}

					?></tfoot><?php
               }
               else{

                   echo '<tr><td colspan="10">'. $langs->trans('WillUseProductWorkstationIfNotSpecified') .'</td></tr>';
               }

                ?>

               </table><?php


            ?></td>
        </tr><?php
        }


		if($user->rights->nomenclature->showPrice)
		{
				// La methode setPrice garde maintenant l'objet marge dans un attribut, pas besoin de le reload 
				// pour rien surtout qu'une commande peut avoir une propal d'origine qui possède des coef custom
				$marge = $n->marge_object;
				
				$PR_coef = price2num($n->totalPRCMO,'MT'); // Prix de revient chargé (on affiche tjr le chargé)
				$price_buy = $n->getBuyPrice(); // prix d'achat total
				$price_to_sell =  $n->getSellPrice(); // prix de vente conseillé total
		        ?>
		        <tr class="liste_total" >
                       <td style="font-weight: bolder;"><?php echo $langs->trans('TotalAmountCostWithCharge', $qty_ref); ?></td>
                       <td colspan="3">&nbsp;</td>
                       <td style="font-weight: bolder; text-align: right;"><?php echo price($PR_coef); ?></td>
                       	<?php echo $formCore->hidden('price_buy', round($price_buy,2)); ?>
		        </tr><?php

				// On affiche aussi à l'unité
		        if($qty_ref!=1 && !empty($qty_ref)) {
	        	?>
				<tr class="liste_total" >
					<td style="font-weight: bolder;"><?php echo $langs->trans('TotalAmountCostWithChargeUnit'); ?></td>
					<td colspan="3">&nbsp;</td>
					<td style="font-weight: bolder; text-align: right;">
					<?php echo price($PR_coef/$qty_ref); ?>
					</td>
				</tr>
        	    <?php
		        }

		       if(!empty($conf->global->NOMENCLATURE_ACTIVATE_DETAILS_COSTS)) {

					  ?><tr class="liste_total" >
		                       <td style="font-weight: bolder;"><?php echo $langs->trans('TotalAmountCostWithChargePMP', $qty_ref); ?></td>
		                       <td colspan="3">&nbsp;</td>
		                       <td style="font-weight: bolder; text-align: right;"><span class="pricePMP"><?php echo price(price2num($n->totalPRCMO_PMP,'MT')); ?></span></td>
				      </tr><?php

				      if(!empty($conf->of->enabled)) {
					      	?><tr class="liste_total" >
			                       <td style="font-weight: bolder;"><?php echo $langs->trans('TotalAmountCostWithChargeOF', $qty_ref); ?></td>
			                       <td colspan="3">&nbsp;</td>
			                       <td style="font-weight: bolder; text-align: right;"><span class="priceOF"><?php echo price(price2num($n->totalPRCMO_OF,'MT')); ?></span></td>
					      	</tr><?php

				      }

				      if($qty_ref!=1 && !empty($qty_ref)) {
	      				?>
	      				<tr class="liste_total" >
	      					<td style="font-weight: bolder;"><?php echo $langs->trans('TotalAmountCostWithChargePMP', 1); ?></td>
	      					<td colspan="3">&nbsp;</td>
	      					<td style="font-weight: bolder; text-align: right;">
	      					<?php echo price(price2num($n->totalPRCMO_PMP/$qty_ref,'MT')); ?>
	      					</td>
	      				</tr>

	      				<tr class="liste_total" >
	      					<td style="font-weight: bolder;"><?php echo $langs->trans('TotalAmountCostWithChargeOF', 1); ?></td>
	      					<td colspan="3">&nbsp;</td>
	      					<td style="font-weight: bolder; text-align: right;">
	      					<?php echo price(price2num($n->totalPRCMO_OF/$qty_ref,'MT')); ?>
	      					</td>
	      				</tr>

	              	    <?php
	      		     }
		        }

		        if(!empty($conf->global->NOMENCLATURE_HIDE_ADVISED_PRICE))
				{
					echo $formCore->hidden('price_to_sell', $price_to_sell);
				}
				else {
		        ?>
		        <tr class="liste_total" >
                       <td style="font-weight: bolder;"><?php echo $langs->trans('PriceConseil', ($marge->tx_object -1)* 100, $qty_ref); ?></td>
                       <td colspan="3">&nbsp;</td>
                       <td style="font-weight: bolder; text-align: right;">
                       	<?php echo price($price_to_sell); ?>
                       	<?php echo $formCore->hidden('price_to_sell', $price_to_sell); ?>
                       </td>
		        </tr>
				
					<?php 
					// On affiche aussi à l'unité
					if($qty_ref!=1 && !empty($qty_ref)) { 
					?>
					<tr class="liste_total" >
						   <td style="font-weight: bolder;"><?php echo $langs->trans('PriceConseilUnit', ($marge->tx_object -1)* 100); ?></td>
						   <td colspan="3">&nbsp;</td>
						   <td style="font-weight: bolder; text-align: right;"><?php echo price($price_to_sell / $qty_ref); ?></td>
					</tr>
					<?php } ?>
		        <?php
		        }
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
                </div>
            </td>
        </tr>
        
        <tr>
			<td colspan="5">
				<div class="tabsAction">
					<?php if ($json == 1) { ?>
                   		<style type="text/css">
                   			.dialogSouldBeZindexed {
                   				z-index:101 !important;  /* Ce z-index avait été ajouté pour un problème de superposition avec les select produits contenu dans la fenêtre mais apparemment on en a plus besoin */
                   				/* => finalement je le remet car je rencontre de nouveau le problème et je le reproduit à chaque fois que je fait plusieurs recherche via les selects (inputs) */
                   				overflow:visible !important; /* Permet de ne pas tronquer le visuel après un ajout */
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
								<input id="nomenclature_bt_clone_nomenclature" type="submit" name="clone_nomenclature" class="butAction" value="<?php echo $langs->trans('CloneNomenclatureFromProduct'); ?>" />
							</div>
						</div>

                   <?php } ?>

					<div class="inline-block divButAction">
						<input type="submit" name="save_nomenclature" class="butAction" value="<?php echo $langs->trans('SaveNomenclature'); ?>" />
					</div>
					<?php if ($json) { ?>
						<div class="inline-block divButAction">
							<input type="submit" name="apply_nomenclature_price" class="butAction" value="<?php echo $langs->trans('ApplyNomenclaturePrice'); ?>" />
						</div>
					<?php } ?>

					<?php $parameters = array(); $reshook = $hookmanager->executeHooks('addMoreActionsButtons', $parameters, $n, $action); ?>
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
global $db,$langs;

	$max_nested_aff_level = empty($conf->global->NOMENCLATURE_MAX_NESTED_AFF_LEVEL) ? 7 : $conf->global->NOMENCLATURE_MAX_NESTED_AFF_LEVEL;
	if($level > $max_nested_aff_level) {
		echo '<div class="error">'.$langs->trans('ThereIsTooLevelHere').'</div>';
		return false;
	}


	$max_level = empty($conf->global->NOMENCLATURE_MAX_NESTED_LEVEL) ? 50 : $conf->global->NOMENCLATURE_MAX_NESTED_LEVEL;
	if($level > $max_level) {
		echo $langs->trans('ThisIsAnInfinitLoop');
		return false;
	}

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

	return true;
}
