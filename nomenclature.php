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
$langs->load("nomenclature@nomenclature");

$product = new Product($db);
$fk_product = GETPOST('fk_product', 'int');
$product_ref = GETPOST('ref', 'alpha');
if ($fk_product || $product_ref) $product->fetch($fk_product, $product_ref);

$qty_ref = (float)GETPOST('qty_ref');

$action= GETPOST('action');

$PDOdb=new TPDOdb;

$fk_object=(int)GETPOST('fk_object');
$fk_nomenclature=(int)GETPOST('fk_nomenclature');
$object_type = GETPOST('object_type');

if(empty($object_type)) {
    $object_type='product';
    $fk_object = $product->id;
}

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

	if (GETPOST('apply_nomenclature_price'))
	{
		$price_buy_init = GETPOST('price_buy');
		$price_to_sell_init = GETPOST('price_to_sell');

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
						$productPropaleLine = new Product($db);
						$productPropaleLine->fetch($fk_product);
						$price_buy = $price_buy_init / $line->qty;
						$price_to_sell = $price_to_sell_init / $line->qty;

						$propal->updateline($fk_object, $price_to_sell, $line->qty, $line->remise_percent, $productPropaleLine->tva_tx, $line->localtax1_tx, $line->localtax2_tx, $line->desc, 'HT', $line->info_bits, $line->special_code, $line->fk_parent_line, $line->skip_update_total, $line->fk_fournprice, $price_buy, $line->product_label, $line->product_type, $line->date_start, $line->date_end, $line->array_options, $line->fk_unit);
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
						$productCommandLine = new Product($db);
						$productCommandLine->fetch($fk_product);
						$price_buy = $price_buy_init / $line->qty;
						$price_to_sell = $price_to_sell_init / $line->qty;

						$commande->updateline($fk_object, $line->desc, $price_to_sell, $line->qty, $line->remise_percent, $productCommandLine->tva_tx, $line->localtax1_tx, $line->localtax2_tx, 'HT', $line->info_bits, $line->date_start, $line->date_end, $line->product_type, $line->fk_parent_line, $line->skip_update_total, $line->fk_fournprice, $price_buy, $line->product_label, $line->special_code, $line->array_options, $line->fk_unit);
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

		$n->setPrice($PDOdb,$n->qty_reference,$n->fk_object,$n->object_type);

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
	_show_product_nomenclature($PDOdb, $product, $qty_ref);
}



$db->close();
function _show_product_nomenclature(&$PDOdb, &$product, $qty_ref) {
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

	    _fiche_nomenclature($PDOdb, $n, $product, $product->id, 'product',$qty_ref);

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

	$coef_qty_price = $n->setPrice($PDOdb,$qty_ref,$fk_object,$object_type);

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

	?>
	<script type="text/javascript">
	$(document).ready(function() {
		$("#det-table>tbody").sortable({
			placeholder: "ui-state-highlight"
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
			placeholder: "ui-state-highlight"
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
                               <td><?php echo $formCore->combo('', 'TNomenclature['.$k.'][code_type]', TNomenclatureDet::getTType($PDOdb), $det->code_type); ?></td>
                               <td><?php
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


                               ?>
                               <td>
                               	<?php echo $det->fk_product>0 ? price($p_nomdet->stock_reel,'',0,1,1,2) : '-'; ?>
                               </td>
                               <td>
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
                               		echo !empty($det->fk_product) ? price($p_nomdet->stock_theorique,'',0,1,1,2) : '-';
                               	?>
                               </td>
                               <td><?php
                               		echo $formCore->texte('', 'TNomenclature['.$k.'][qty]', $det->qty, 7,100);
							   		if($coef_qty_price != 1) echo '<br /> x '.price($coef_qty_price,'','',2,2) ;
							    ?></td>


                               <td><?php
				if($n->getId()>0) {
					?><a class="tojs" href="<?php echo dol_buildpath('/nomenclature/nomenclature.php',1) ?>?action=delete_nomenclature_detail&k=<?php echo $k ?>&fk_nomenclature=<?php
                               echo $n->getId() ?>&fk_product=<?php echo $product->id ?>&fk_object=<?php
                               echo $fk_object ?>&object_type=<?php echo $object_type ?>&qty_ref=<?php
                               echo $qty_ref ?>&fk_origin=<?php echo GETPOST('fk_origin', 'int'); ?>&json=1"><?php echo img_delete() ?></a><?php
				}
				?></td>

                               <?php

	                            if($user->rights->nomenclature->showPrice) {

	                            	$price = $det->calculate_price;
									$price_charge = $det->charged_price;

									echo '<td align="right" valign="bottom">';
									if(!empty($conf->global->NOMENCLATURE_ACTIVATE_DETAILS_COSTS)) {
										echo price($det->calculate_price).img_help(1,$langs->trans('PricePA'));
										echo '<span class="pricePMP"><br />'.price($det->calculate_price_pmp).img_help(1,$langs->trans('PricePMP')).'</span>';
										if(!empty($conf->of->enabled)) echo '<span class="priceOF"><br />'.price($det->calculate_price_of).img_help(1,$langs->trans('PriceOF')).'</span>';
									}
									else{
										echo price($price);
									}


                                	echo '</td>';
									echo '<td align="right" valign="bottom">';
									if(!empty($conf->global->NOMENCLATURE_ACTIVATE_DETAILS_COSTS)) {
										echo price($det->charged_price);
										echo '<span class="pricePMP"><br />'.price($det->charged_price_pmp).'</span>';
										if(!empty($conf->of->enabled)) echo '<span class="priceOF"><br />'.price($det->charged_price_of).'</span>';
									}
									else{
                                    	echo price($price_charge);
									}
                                	echo '</td>';
									echo '<td align="right" valign="bottom">';
                                    echo '<input style="text-align:right;" name="TNomenclature['.$k.'][price]" value="'.price($det->price).'" size="5" />';
                                	echo '</td>';
	                            }
                               ?>
                           </tr>
                           <?php

                       }

					?></tbody><tfoot><?php

				       if($user->rights->nomenclature->showPrice) {
				       		$colspan = 5;
							if($conf->global->FOURN_PRODUCT_AVAILABILITY > 0) $colspan += 1;
                       ?>
                       <tr class="liste_total">
                           <td ><?php echo $langs->trans('Total'); ?></td>
                           <td colspan="<?php echo $colspan; ?>">&nbsp;</td>
                           <td align="right"><?php echo price($n->totalPR); ?></td>
                           <td align="right"><?php echo price($n->totalPRC); ?></td>
                           <td align="right"><?php /*echo price(round($total_produit_coef_final,2));*/ ?></td>

                       </tr>
                       <?php

                       if(!empty($conf->global->NOMENCLATURE_ACTIVATE_DETAILS_COSTS)) {
	                        ?>
	                       <tr class="liste_total">
	                           <td ><?php echo $langs->trans('TotalPricePMP'); ?></td>
	                           <td colspan="<?php echo $colspan; ?>">&nbsp;</td>
	                           <td align="right"><?php echo price($n->totalPR_PMP); ?></td>
	                           <td align="right"><?php echo price($n->totalPRC_PMP); ?></td>
	                           <td align="right"><?php /*echo price(round($total_produit_coef_final,2));*/ ?></td>

	                       </tr><?php

	                       if(!empty($conf->of->enabled)) {

		                       ?><tr class="liste_total">
		                           <td ><?php echo $langs->trans('TotalPriceOF'); ?></td>
		                           <td colspan="<?php echo $colspan; ?>">&nbsp;</td>
		                           <td align="right"><?php echo price($n->totalPR_OF); ?></td>
		                           <td align="right"><?php echo price($n->totalPRC_OF); ?></td>
		                           <td align="right"><?php /*echo price(round($total_produit_coef_final,2));*/ ?></td>

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
        <?php
       if($conf->workstation->enabled) {

       ?><tr>
           <td colspan="5"><?php
               ?>
               <table class="liste" width="100%" id="workstation-table">
               	<thead>
               <tr class="liste_titre">
                   <!--<td class="liste_titre"><?php echo $langs->trans('Type'); ?></td>-->
                   <td class="liste_titre" colspan="2"><?php echo $langs->trans('Worstations'); ?></td>
                   <td class="liste_titre"><?php echo $langs->trans('QtyPrepare'); ?></td>
                   <td class="liste_titre"><?php echo $langs->trans('QtyFabrication'); ?></td>
                   <td class="liste_titre"><?php echo $langs->trans('Qty'); ?></td>
                   <td class="liste_titre">&nbsp;</td>
                 <?php if($user->rights->nomenclature->showPrice) {
                 	?><td class="liste_titre" align="right"><?php echo $langs->trans('AmountCostWithCharge'); ?></td><?php }

                 ?>

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
                       	   <td colspan="2"><?php

                                echo $ws->workstation->getNomUrl(1);
                                echo $formCore->zonetexte('', 'TNomenclatureWorkstation['.$k.'][note_private]', $ws->note_private, 80, 1, ' style="width:95%;"');
                           ?></td>
                           <td ><?php echo $formCore->texte('', 'TNomenclatureWorkstation['.$k.'][nb_hour_prepare]', $ws->nb_hour_prepare, 7,100) ?></td>
                           <td ><?php
                           		echo $formCore->texte('', 'TNomenclatureWorkstation['.$k.'][nb_hour_manufacture]', $ws->nb_hour_manufacture, 7,100);
                           		if($coef_qty_price != 1) echo '<br /> x '.price($coef_qty_price,'','',2,2) ;
                           	?></td>
                           <td ><?php
                           		echo $ws->nb_hour_calculate.'h';
						   ?></td>

                           <td ><?php if($n->getId()>0) { ?><a class="tojs" href="<?php echo dol_buildpath('/nomenclature/nomenclature.php',1); ?>?action=delete_ws&k=<?php echo $k ?>&fk_product=<?php echo $product->id ?>&fk_nomenclature=<?php
                           echo $n->getId() ?>&fk_object=<?php echo $fk_object ?>&object_type=<?php
                           echo $object_type ?>&qty_ref=<?php echo $qty_ref ?>&fk_origin=<?php echo GETPOST('fk_origin', 'int'); ?>&json=<?php echo $json; ?>"><?php echo img_delete() ?></a><?php } ?></td>
                           <?php

                           if($user->rights->nomenclature->showPrice) {

								$price_charge = ($ws->price) ? $ws->price : $ws->calculate_price; //$ws->price = à la dernière colonne à droite pour le coût final (perso)
								$total_mo+=$price_charge;

	                           echo '<td align="right" valign="bottom">';
                               echo price($price_charge) ;

							   if(!empty($conf->global->NOMENCLATURE_ACTIVATE_DETAILS_COSTS) && !empty($conf->of->enabled)) {
							   		  echo '<span class="priceOF"><br />'.price($ws->calculate_price_of,'','',1,1,2).img_help(1, $langs->trans('priceMO_OF')).'</span>' ;
							   }

	                           echo '</td>';
	                      }
                       ?>
                       </tr>

                       <?php


                   }

				?></tbody><tfoot><?php

					if($user->rights->nomenclature->showPrice) {
	                    ?><tr class="liste_total">
	                           <td><?php echo $langs->trans('Total'); ?></td>
	                           <td colspan="4">&nbsp;</td>
	                           <td>&nbsp;</td>
	                           <td align="right"><?php echo price($n->totalMO); ?></td>

	                    </tr><?php
	                     if(!empty($conf->global->NOMENCLATURE_ACTIVATE_DETAILS_COSTS) && !empty($conf->of->enabled)) {
		                    ?><tr class="liste_total">
		                           <td><?php echo $langs->trans('TotalMO_OF'); ?></td>
		                           <td colspan="4">&nbsp;</td>
		                           <td>&nbsp;</td>
		                           <td align="right"><?php echo price($n->totalMO_OF,'','',1,1,2); ?></td>

		                    </tr><?php
						 }
					}

					?></tfoot><?php
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
				$PR_coef = $n->totalMO+$n->totalPRC;
				$price_buy = $n->totalMO+$n->totalPRC;
				$price_to_sell = $n->totalPV;
				if(empty($qty_ref)) $qty_ref = $n->qty_reference;
		        ?>
		        <tr class="liste_total" >
                       <td style="font-weight: bolder;"><?php echo $langs->trans('TotalAmountCostWithCharge', $qty_ref); ?></td>
                       <td colspan="3">&nbsp;</td>
                       <td style="font-weight: bolder; text-align: right;"><?php echo price($PR_coef); ?></td>
                       	<?php echo $formCore->hidden('price_buy', round($price_buy,2)); ?>
		        </tr><?php

		        if($qty_ref!=1 && !empty($qty_ref)) {
	        	?>
				<tr class="liste_total" >
					<td style="font-weight: bolder;"><?php echo $langs->trans('TotalAmountCostWithCharge', 1); ?></td>
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
		                       <td style="font-weight: bolder; text-align: right;"><span class="pricePMP"><?php echo price($n->totalPRCMO_PMP); ?></span></td>
				      </tr><?php

				      if(!empty($conf->of->enabled)) {
					      	?><tr class="liste_total" >
			                       <td style="font-weight: bolder;"><?php echo $langs->trans('TotalAmountCostWithChargeOF', $qty_ref); ?></td>
			                       <td colspan="3">&nbsp;</td>
			                       <td style="font-weight: bolder; text-align: right;"><span class="priceOF"><?php echo price($n->totalPRCMO_OF); ?></span></td>
					      	</tr><?php

				      }

				      if($qty_ref!=1 && !empty($qty_ref)) {
	      				?>
	      				<tr class="liste_total" >
	      					<td style="font-weight: bolder;"><?php echo $langs->trans('TotalAmountCostWithChargePMP', 1); ?></td>
	      					<td colspan="3">&nbsp;</td>
	      					<td style="font-weight: bolder; text-align: right;">
	      					<?php echo price($n->totalPRCMO_PMP/$qty_ref); ?>
	      					</td>
	      				</tr>

	      				<tr class="liste_total" >
	      					<td style="font-weight: bolder;"><?php echo $langs->trans('TotalAmountCostWithChargeOF', 1); ?></td>
	      					<td colspan="3">&nbsp;</td>
	      					<td style="font-weight: bolder; text-align: right;">
	      					<?php echo price($n->totalPRCMO_OF/$qty_ref); ?>
	      					</td>
	      				</tr>

	              	    <?php
	      		     }
		        }

		        if(empty($conf->global->NOMENCLATURE_HIDE_ADVISED_PRICE)) {
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
		                    <input type="submit" name="add_nomenclature" class="butAction" value="<?php echo $langs->trans('AddProductNomenclature'); ?>" />
		                </div>

                   </div>

                   <?php if ($json == 1) { ?>
                   		<style type="text/css">
                   			.dialogSouldBeZindexed {
                   				/*z-index:101 !important;  Ce z-index avait été ajouté pour un problème de superposition avec les select produits contenu dans la fenêtre mais apparemment on en a plus besoin */
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
