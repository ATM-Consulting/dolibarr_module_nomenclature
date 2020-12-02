<?php

	require 'config.php';
	dol_include_once('/nomenclature/class/nomenclature.class.php');

	$langs->load('nomenclature@nomenclature');

	$object_type = GETPOST('object', 'none');
	$id = GETPOST('id', 'int');

	if(GETPOST('save', 'alpha')=='ok') setEventMessage($langs->trans('Saved'));

	if($object_type =='propal') {
		dol_include_once('/comm/propal/class/propal.class.php');
		$object = new Propal($db);
		$object->fetch($id);

	}
        else if($object_type =='commande') {
                dol_include_once('/commande/class/commande.class.php');
                $object = new Commande($db);
                $object->fetch($id);

        }
	else {
		exit('? object type ?');
	}

	if(empty($object))exit;
	$PDOdb=new TPDOdb;

	$TProductAlreadyInPage=array();

	// redirrection sur une vue global non modifiable
	if( ($object->fk_statut>0 || $object->statut>0) && in_array($object_type, array('propal','commande')) ) {
	    $url = dol_buildpath('/nomenclature/nomenclature-detail.php' , 1).'?id='.$object->id.'&object='.$object_type;
	    header('Location: '.$url);
	    exit;
	}

	_drawlines($object, $object_type);

function _drawHeader($object, $object_type) {
global $db,$langs,$conf,$PDOdb;

	if($object_type == 'propal') {
		dol_include_once('/core/lib/propal.lib.php');
		$head = propal_prepare_head($object);
		dol_fiche_head($head, 'nomenclature', $langs->trans('Proposal'), 0, 'propal');

		/*
		 * Propal synthese pour rappel
		 */
		print '<table class="border" width="100%">';

		// Ref
		print '<tr><td width="25%">'.$langs->trans('Ref').'</td><td colspan="3">';
		print $object->ref;
		print '</td></tr>';

		// Ref client
		print '<tr><td>';
		print '<table class="nobordernopadding" width="100%"><tr><td class="nowrap">';
		print $langs->trans('RefCustomer').'</td><td align="left">';
		print '</td>';
		print '</tr></table>';
		print '</td><td colspan="3">';
		print $object->ref_client;
		print '</td>';
		print '</tr>';
		print '</table>';

	}
	else if($object_type == 'commande') {
                dol_include_once('/core/lib/order.lib.php');
                $head = commande_prepare_head($object);
                dol_fiche_head($head, 'nomenclature', $langs->trans('CustomerOrder'), 0, 'order');

                /*
                 * Propal synthese pour rappel
                 */
                print '<table class="border" width="100%">';

                // Ref
                print '<tr><td width="25%">'.$langs->trans('Ref').'</td><td colspan="3">';
                print $object->ref;
                print '</td></tr>';

                // Ref client
                print '<tr><td>';
                print '<table class="nobordernopadding" width="100%"><tr><td class="nowrap">';
                print $langs->trans('RefCustomer').'</td><td align="left">';
                print '</td>';
                print '</tr></table>';
                print '</td><td colspan="3">';
                print $object->ref_client;
                print '</td>';
                print '</tr>';
                print '</table>';

        }


	if($object->fk_statut>0 || $object->statut>0) {

		echo '<div class="error">'. $langs->trans('StatusOfObjectAvoidEdit') .'</div>';


		llxFooter();
		exit;
	}



	?><script type="text/javascript">
		var fk_object=<?php echo $object->id; ?>;
		var object_type="<?php echo $object_type; ?>";
		var NOMENCLATURE_SPEED_CLICK_SELECT = <?php echo (int)$conf->global->NOMENCLATURE_SPEED_CLICK_SELECT; ?>;

		function editLine(fk_line) {

			url="<?php
				if((float) DOL_VERSION >= 4.0 && $object_type=='propal') echo dol_buildpath('/comm/propal/card.php?id='.$object->id,1);
				elseif($object_type=='propal') echo dol_buildpath('/comm/propal.php?id='.$object->id,1);
				else if($object_type=='commande')echo dol_buildpath('/commande/card.php?id='.$object->id,1);
			?>&action=editline&lineid="+fk_line;

			$('div#dialog-edit-line').remove();
			$('body').append('<div id="dialog-edit-line"></div>');
			$('div#dialog-edit-line').dialog({
				title: "<?php echo $langs->trans('EditLine') ?>"
				,width:"80%"
				,modal:true
			});

			$.ajax({
				url:url
			}).done(function(data) {

				$form = $(data).find('form#addproduct');
				$form.find('input[name=cancel]').remove();
				$form.find('tr[id]').not('#row-'+fk_line).remove();

				$form.submit(function() {
					if (typeof CKEDITOR == "object" && typeof CKEDITOR.instances != "undefined" && CKEDITOR.instances['product_desc'] != "undefined") {
						$form.find('textarea#product_desc').val(CKEDITOR.instances['product_desc'].getData());
					}

					$.post($(this).attr('action'), $(this).serialize()+'&save=1', function() {

					});

					$('div#dialog-edit-line').dialog('close');

					return false;


				});

				$('div#dialog-edit-line').html($form);
			});

		}
	</script><?php

}

function _drawlines(&$object, $object_type) {
	global $db,$langs,$conf,$PDOdb,$TProductAlreadyInPage;

	llxHeader('', 'Nomenclatures', '', '', 0, 0, array('/nomenclature/js/speed.js','/nomenclature/js/jquery-sortable-lists.min.js'), array('/nomenclature/css/speed.css'));

	_drawHeader($object, $object_type);

	$formDoli=new Form($db);
	$formCore=new TFormCore;
	echo '<div id="addto" style="float:right; width:200px;">';

		if(!empty($conf->global->NOMENCLATURE_ALLOW_JUST_MP)) {
			print $formDoli->select_produits('', 'fk_product', '', 0,0,-1,0);
		}
		else{
			print $formDoli->select_produits('', 'fk_product', '', 0,0,-1);
		}

		echo $formCore->bt($langs->trans('AddProductNomenclature'), 'AddProductNomenclature');
		echo '<hr />';

		echo $formCore->combo('', 'fk_new_workstation',TWorkstation::getWorstations($PDOdb, false, true), -1);
		echo $formCore->bt($langs->trans('AddWorkstation'), 'AddWorkstation');

		echo '<hr />';
		echo $formCore->bt($langs->trans('SaveAll'), 'SaveAll');

	echo '</div>';

	echo '<ul id="speednomenclature" container-type="main" class="lines '.$object->element.'">';

	foreach($object->lines as $k=>&$line) {

		if($line->product_type == 9) {
			$class="lineObject special";
			$line_type="special";
		}
		else {
			$class="lineObject";
			$line_type="line";
		}

		echo '<li k="'.$k.'" class="'.$class.'" line-type="'.$line_type.'" object_type="'.$object->element.'" id="line-'.$line->id.'"  fk_object="'.$line->id.'" fk_product="'.$line->fk_product.'">';

		echo '<div>';

		if($line->product_type == 0 || $line->product_type == 1) {
			echo '<a href="javascript:editLine('.$line->id.');" class="editline clicable">'.img_edit($langs->trans('EditLine')).'</a>';
			if(!empty($conf->global->NOMENCLATURE_SPEED_CLICK_SELECT)) {

				echo '<a style="float:right;" href="javascript:;" class="clickToSelect clicable">'.img_picto('Sélectionner cette ligne','object_opensurvey.png',' class="clicable"').'</a>';

			}
		}
		$label = !empty($line->label) ? $line->label : (empty($line->libelle) ? $line->desc : $line->libelle);

		if($line->fk_product>0) {
			$product = new Product($db);
			$product->fetch($line->fk_product);

			echo '<div class="label">'.$product->getNomUrl(1).' '.$product->label.'</div>';
		}
		else if($line->product_type == 9 ) {
			/* ligne titre */
			if($line->qty>=90) {
				echo '<div class="total label">'.(100-$line->qty).'. '.$label.'</div>';
			//	var_dump($line);exit;
			}
			else {
				echo '<div class="title label">'.$line->qty.'. '.$label.'</div>';
			}


		}
		else {
			/* ligne libre */
			echo '<div class="free label">'.$label.'</div>';
		}

		if($line->product_type == 0 || $line->product_type == 1) echo '<div class="qty"><input rel="qty" value="'.$line->qty.'" class="flat qty clicable" size="5" /></div>';

		echo '</div>';

		if($line->product_type == 0 || $line->product_type == 1) _drawnomenclature($line->id, $object->element,$line->fk_product,$line->qty);

		echo '</li>';


	}

	echo '</ul>';
	?>
	<div class="logme"></div>

	<div style="text-align: left;">
		<div class="inline-block divButAction"><a href="nomenclature-detail.php?id=<?php echo GETPOST('id', 'int') ?>&object=<?php echo GETPOST('object', 'none') ?>" class="butAction">Liste produits et MO nécessaires</a></div>

	</div>

	<?php
	dol_fiche_end();
	llxFooter();
}
function _drawnomenclature($fk_object, $object_type,$fk_product,$qty, $level = 1) {
	global $db,$langs,$conf,$PDOdb,$TProductAlreadyInPage;

	$max_nested_aff_level = empty($conf->global->NOMENCLATURE_MAX_NESTED_AFF_LEVEL) ? 7 : $conf->global->NOMENCLATURE_MAX_NESTED_AFF_LEVEL;
	if($level > $max_nested_aff_level) {
		echo '<div class="error">'.$langs->trans('ThereIsTooLevelHere').'</div>';
		return false;
	}


	$nomenclature=new TNomenclature;
	$nomenclature->loadByObjectId($PDOdb, $fk_object, $object_type, true,$fk_product,$qty);
/*if($fk_object == 811) {
        echo 1;
var_dump( $object_type,$fk_product,$qty , $nomenclature->TNomenclatureAll);
}*/

	if(!empty($TProductAlreadyInPage[$fk_object.'_'. $object_type])) {
		echo '<ul class="lines nomenclature" container-type="nomenclature" fk_nomenclature="'.$nomenclature->getId().'">';
		echo '<li class="nomenclature clicable" no-hierarchie-parse="1" id="nomenclature-nouse-'.$nomenclature->getId().'-'.$fk_product.'">Nomenclature déjà affichée <a href="#" onclick="window.scrollTo(0, $(\'li[fk_object='.$fk_product.'][object_type=product]\').first().offset().top ); ">ici</a></li>';
		echo '</ul>';
	}
	else if(!empty($nomenclature->TNomenclatureAll)) {

		$TProductAlreadyInPage[$fk_object.'_'. $object_type] = 1;

		if($nomenclature->iExist) {
			echo '<ul class="lines nomenclature" container-type="nomenclature" fk_nomenclature="'.$nomenclature->getId().'">';
		}
		else {
			echo '<ul class="lines notanomenclature" container-type="nomenclature" fk_nomenclature="0" fk_original_nomenclature="'.$nomenclature->fk_nomenclature_parent.'">';
			echo '<div>'.$langs->trans('PseudoNomenclature') .img_help('',$langs->trans('PseudoNomenclatureInfo')  ).'</div>';
		}

		foreach($nomenclature->TNomenclatureAll as $k=>&$line) {

			if(get_class($line) === 'TNomenclatureDet' ) {

				$product = new Product($db);
				if($line->fk_product>0 && $product->fetch($line->fk_product)>0) {
					$id = $line->getId() > 0 ? $line->getId() : $nomenclature->fk_nomenclature_parent.'-'.$k;


					echo '<li class="nomenclature" k="'.$k.'" line-type="nomenclature" id="nomenclature-product-'.$id.'" object_type="product" fk_object="'.$line->fk_product.'">';
					echo '<div class="clicable" rel="delete">'.img_delete('',' class="clicable"').'</div>';
					if(!empty($conf->global->NOMENCLATURE_SPEED_CLICK_SELECT)) {

						echo '<a style="float:right;" href="javascript:;" class="clickToSelect clicable">'.img_picto('Sélectionner cette ligne','object_opensurvey.png',' class="clicable"').'</a>';

					}
					echo '<div>';
						echo '<div class="label">'.($line->unifyRang+1).'. '. $product->getNomUrl(1).' '.$product->label.'</div>';
						echo '<div class="qty"><input rel="qty" value="'.$line->qty.'" class="flat qty clicable" size="5" />';
						if($qty > 1) echo '&nbsp;(x '.$qty.')';
						echo '</div>';

					echo '</div>';
						_drawnomenclature($product->id, 'product',$product->id,$line->qty * $qty,$level+1);
					echo '</li>';
				}

			}
			else {
				echo '<li class="nomenclature workstation" line-type="workstation"  k="'.$k.'" object_type="workstation" id="nomenclature-ws-'.$line->getId().'" fk_object="'.$line->workstation->getId().'">';
				echo '<div class="clicable" rel="delete">'.img_delete('',' class="clicable"').'</div>';
				echo '<div>';
					echo '<div class="label">'.($line->unifyRang+1).'. '.$line->workstation->name.'</div>';
					echo '<div class="qtyws" >
						<input rel="nb_hour_prepare" value="'.$line->nb_hour_prepare.'" class="flat qty clicable" size="5" title="Heure(s) de préparation" />
						<input rel="nb_hour_manufacture" value="'.$line->nb_hour_manufacture.'" class="flat qty clicable" size="5" title="Heure(s) de fabrication" />';
					if($qty > 1) echo '&nbsp;(x '.$qty.')';
					echo '</div>
				</div>';
				echo '</li>';
			}

		}

		echo '</ul>';
	}

	return true;

}
