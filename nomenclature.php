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
require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';

if(!empty($conf->workstationatm->enabled)) {
    dol_include_once('/workstationatm/class/workstation.class.php');
}

$hookmanager->initHooks(array('nomenclaturecard'));
$newToken = function_exists('newToken') ? newToken() : $_SESSION['newtoken'];
$langs->load("stocks");
$langs->load("products");
$langs->load("nomenclature@nomenclature");

$product = new Product($db);
$fk_product = GETPOST('fk_product', 'int');
$product_ref = GETPOST('ref', 'alpha');
if ($fk_product || $product_ref) $product->fetch($fk_product, $product_ref);

$qty_ref = (float)GETPOST('qty_ref', 'int'); // il s'agit de la qty de la ligne de document, si vide alors il faudra utiliser qty_reference de la nomenclature

$action= GETPOST('action', 'alpha');

$PDOdb=new TPDOdb;

$fk_object= (int)GETPOST('fk_object', 'int');
$fk_nomenclature= (int)GETPOST('fk_nomenclature', 'int');
$object_type = GETPOST('object_type', 'none');
$fk_origin = GETPOST('fk_origin', 'int');

$disableAnchorRedirection = GETPOST('disableAnchorRedirection', 'none');

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
	    $n->load($PDOdb, (int)GETPOST('fk_nomenclature', 'int'));
	    $n->delete($PDOdb);

	    setEventMessage('NomenclatureDeleted');

	}
	else if($action==='clone_nomenclature') {

		cloneNomenclatureFromProduct($PDOdb, GETPOST('fk_product_clone', 'int'), $fk_object, $object_type);
	}
	else if($action==='add_nomenclature') {

	    $n=new TNomenclature;
	    $n->set_values($_REQUEST);
	    $res = $n->save($PDOdb);
	    if($res>0){
	    	setEventMessage($langs->trans('BomAdded'));
		}
	    else{
			setEventMessage($langs->trans('BomAddError'), 'errors');
		}
	}
	else if($action==='add_fk_nomenclature') {
		//TODO ajouter les enfants de la nomenclature passé en post à la nomenclature courrante

	}
	else if($action === 'delete_nomenclature_detail') {

		$n=new TNomenclature;

	    $n->load($PDOdb, $fk_nomenclature);

	    if($n->getId()>0) {

	    $n->TNomenclatureDet[GETPOST('k', 'none')]->to_delete = true;

	    $n->save($PDOdb);
	   }
	}
	else if($action === 'delete_ws') {
	    $n=new TNomenclature;
	 //   $PDOdb->debug = true;
	    $n->load($PDOdb, $fk_nomenclature);

	    if($n->getId()>0) {
	    	$k = (int)GETPOST('k', 'int');
	//var_dump( $fk_nomenclature,$k,$n->TNomenclatureWorkstation);
		$n->TNomenclatureWorkstation[$k]->to_delete = true;
		$n->save($PDOdb);
	    }
	}
	else if($action==='save_nomenclature') {

		if (GETPOST('clone_nomenclature', 'none'))
		{
			$n=new TNomenclature;
			$n->load($PDOdb, $fk_nomenclature);
			$n->delete($PDOdb);

			$res = cloneNomenclatureFromProduct($PDOdb, (int)GETPOST('fk_clone_from_product', 'int'), $fk_object, $object_type);

		}
		else
		{
			$anchorTag = '';
			$n=new TNomenclature;

		    if($fk_nomenclature>0) $n->load($PDOdb, $fk_nomenclature, false, $product->id , $qty_ref, $object_type, $fk_origin);
		    else $n->loadByObjectId($PDOdb, $fk_object, $object_type,true, $product->id, $qty_ref, $fk_origin); // si pas de fk_nomenclature, alors on provient d'un document, donc $qty_ref tjr passé en param

			//Cas où on sauvegarde depuis une ligne et qu'il faut dupliquer la nomenclature
			if(!$n->iExist && GETPOST('type_object', 'none')!='product') {
				$n->reinit();
			}

			//vérification titre déjà existant
			if(!empty($conf->global->NOMENCLATURE_UNIQUE_TITLE) && TNomenclature::getRightToSaveTitle(GETPOST('title', 'alphanohtml')) <= 0 && !empty(GETPOST('title', 'alphanohtml'))) {
				//si déjà existant on donne au $_POST le titre précédent pour ne pas que le nouveau soit enregistré
				$_POST['title'] = $n->title ;
				setEventMessage('NomenclatureTitleWarning', 'warnings');
			}
			$n->set_values($_POST);

		    $n->is_default = (int)GETPOST('is_default', 'int');

			if($n->is_default>0) TNomenclature::resetDefaultNomenclature($PDOdb, $n->fk_product);

            //Cas ou l'on déplace une ligne
		    if(!empty($_POST['TNomenclature'])) {
		    	// Réorganisation des clefs du tableau au cas où l'odre a été changé par déplacement des lignes
				$tab = array();
				foreach($_POST['TNomenclature'] as $val) $tab[] = $val;

		        foreach($tab as $k=>$TDetValues) {
                    if(empty($n->TNomenclatureDet[$k])) $n->TNomenclatureDet[$k] = new TNomenclatureDet;
		            $n->TNomenclatureDet[$k]->set_values($TDetValues);

		            if(isset($_POST['TNomenclature_'.$k.'_workstations'])) {
		            	$n->TNomenclatureDet[$k]->workstations = implode(',', $_POST['TNomenclature_'.$k.'_workstations']);
		            }

		        }
		    }


		    if(!empty($_POST['TNomenclatureWorkstation'])) {
		        foreach($_POST['TNomenclatureWorkstation'] as $k=>$TDetValues) {
                    if(!empty($n->TNomenclatureWorkstation[$k])) $n->TNomenclatureWorkstation[$k]->set_values($TDetValues);
		        }
		    }

            //Cas ou l'on ajoute un produit dans la nomenclature
		    $fk_new_product = GETPOST('fk_new_product_'.$n->getId(), 'none');
		    $fk_new_product_qty = GETPOST('fk_new_product_qty_'.$n->getId(), 'none');
		    if(GETPOST('add_nomenclature', 'none') && $fk_new_product>0) {

				$last_det = end($n->TNomenclatureDet);
                if(empty($last_det->rowid))$last_det->rowid = 0;
				$url = dol_buildpath('nomenclature/nomenclature.php', 2).'?fk_product='.$n->fk_object.'&fk_nomenclature='.$n->getId().'#line_'.(intval($last_det->rowid));
				$res = $n->addProduct($PDOdb, $fk_new_product, $fk_new_product_qty);

				if(empty($res)) {
					$p_err= new Product($db);
					$p_err->fetch($fk_new_product);

					setEventMessage($langs->trans('ThisProductCreateAnInfinitLoop').' '.$p_err->getNomUrl(0),'errors');

					header("location: ".$url, true);
					exit;

				} elseif ($n->object_type === 'product') {
                    $last_det = end($n->TNomenclatureDet);
                    $url = dol_buildpath('nomenclature/nomenclature.php', 2).'?fk_product='.$n->fk_object.'&fk_nomenclature='.$n->getId().'#line_'.(intval($last_det->rowid));

					if(empty($disableAnchorRedirection)){
						header("location: ".$url, true);
						exit;
					}
                }
            }

            //Cas où l'on ajoute un nouveau poste à charge
		    $fk_new_workstation = GETPOST('fk_new_workstation', 'none');
		    if(GETPOST('add_workstation', 'none') && $fk_new_workstation>0 ) {
		        $k = $n->addChild($PDOdb, 'TNomenclatureWorkstation');
		        $det = &$n->TNomenclatureWorkstation[$k];
				/** @var TNomenclatureWorkstation $det */
		        $det->fk_workstation = $fk_new_workstation;
		        $det->rang = $k+1;
		        $anchorTag = '#nomenclature-ws-item-k-'.$k; // pas le choix de passer par k car n'est pas encore enregistré
		    }

		    // prevent multiple event from ajax call
		    if(empty($_SESSION['dol_events']['mesgs']) || (!empty($_SESSION['dol_events']['mesgs']) && !in_array($langs->trans('NomenclatureSaved'), $_SESSION['dol_events']['mesgs'])) )
		    {
		        setEventMessage($langs->trans('NomenclatureSaved'));
		    }


            //Mise à jour des prix de la nomenclature
			$n->setPrice($PDOdb,$qty_ref,$n->fk_object,$n->object_type, $fk_origin);

		    $n->save($PDOdb);

			// Fait l'update du PA et PU de la ligne si nécessaire
			_updateObjectLine($n, $object_type, $fk_object, (int)GETPOST('fk_origin', 'int'), GETPOST('apply_nomenclature_price', 'none'));

			if(empty($disableAnchorRedirection)){
				header("Location: ".$_SERVER["PHP_SELF"].'?fk_product='.intval($fk_product)."&fk_nomenclature=".$n->id.$anchorTag);
				exit;
			}
		}

	}
	else if ($action == 'confirm_create_stock' && !empty($conf->global->NOMENCLATURE_ALLOW_MVT_STOCK_FROM_NOMEN))
	{
		$fk_nomenclature_used = GETPOST('fk_nomenclature_used', 'int');
		$fk_warehouse_to_make = GETPOST('fk_warehouse_to_make', 'int');
		$fk_warehouse_needed = GETPOST('fk_warehouse_needed', 'int');
		$qty = GETPOST('nomenclature_qty_to_create', 'int');
		$use_subbom = GETPOST('use_subbom', 'int');

		$n = new TNomenclature;
		$n->load($PDOdb, $fk_nomenclature_used);
        $res = $n->addMvtStock($qty, $fk_warehouse_to_make, $fk_warehouse_needed, $use_subbom);
		if ($res < 0)
		{
			setEventMessages('', $n->errors, 'errors');
			header('Location: '.dol_buildpath('/nomenclature/nomenclature.php', 1).'?fk_product='.$product->id.'&action=create_stock&fk_nomenclature_used='.$n->getId().'&qty_reference='.$qty.'&fk_warehouse_to_make='.$fk_warehouse_to_make.'&fk_warehouse_needed='.$fk_warehouse_needed);
			exit;
		}
		else
		{
			setEventMessage($langs->trans('NomenclatureMvtOk'));
			header('Location: '.dol_buildpath('/nomenclature/nomenclature.php', 1).'?fk_product='.$product->id);
			exit;
		}
	}
}

if($object_type != 'product') {

    $langs->load('nomenclature@nomenclature');
    $origin_object_id = (int)GETPOST('fk_origin', 'int');
    $n=new TNomenclature;
    $n->loadByObjectId($PDOdb,$fk_object, $object_type, false, $product->id, $qty_ref, $origin_object_id);
    $readonly = $object->statut > 0;
    _fiche_nomenclature($PDOdb, $n, $product, $object, $fk_object, $object_type, $qty_ref, $readonly);
    print '<script type="text/javascript" src="'.dol_buildpath('nomenclature/js/searchproductcategory.js.php',1).'"></script>';
}
else{
	_show_product_nomenclature($PDOdb, $product, $object);
}

$db->close();

/**
 * @param TPDOdb $PDOdb
 * @param Product $product
 * @param CommonObject $object
 */
function _show_product_nomenclature(&$PDOdb, &$product, &$object) {
	global $user, $langs, $db, $conf;

	llxHeader('',$langs->trans('Nomenclature'));
	$newToken = function_exists('newToken') ? newToken() : $_SESSION['newtoken'];
	$form = new Form($db);
	$formconfirm = getFormConfirmNomenclature($form, $product, GETPOST('fk_nomenclature_used', 'none'), GETPOST('action', 'alpha'), GETPOST('qty_reference', 'none'));
	if (!empty($formconfirm)) echo $formconfirm;

    $head=product_prepare_head($product, $user);
	$titre=$langs->trans('Nomenclature');
	$picto=($product->type==1?'service':'product');
	dol_fiche_head($head, 'nomenclature', $titre, 0, $picto);

	if ((float) DOL_VERSION >= 4.0) dol_banner_tab($product, 'ref', '', (!empty($user->societe_id)?0:1), 'ref');
	else headerProduct($product);

	?><script type="text/javascript">
		function uncheckOther(obj)
		{
			$("input[name=is_default]").not($(obj)).prop("checked", false);
		}
		function deleteNomenc(fk_nomenclature) {

		    if(window.confirm('Vous-êtes sûr ?')) {

		        document.location.href="?action=delete_nomenclature&fk_product=<?php echo $product->id; ?>&fk_nomenclature="+fk_nomenclature+"&token=<?php echo $newToken; ?>"

		    }


		}
		$(document).ready(function() {
            $("input[name=clone_nomenclature]").click(function() {
                document.location.href="?action=clone_nomenclature&fk_product=<?php echo $product->id; ?>&fk_product_clone="+$("#fk_clone_from_product").val();
            });

            // Pas le choix à cause de l'accordéon le hash par en vrille du coup un petit set timeout et c'est bon
			setTimeout(function () {
				if(window.location.hash){
					var hash = window.location.hash;

					$('html, body').animate({
						scrollTop: $(hash).offset().top -80
					}, 300, 'swing');
				}
			}, 500);

		});

	</script><?php

	$TNomenclature = TNomenclature::get($PDOdb, $product->id);

    if (GETPOST('optioncss', 'none') !== 'print')
    {
	?>
	<div class="tabsAction">
		<div class="inline-block divButAction">
			<a href="?action=add_nomenclature&fk_product=<?php echo $product->id ?>&fk_object=<?php echo $product->id ?>&token=<?php echo $newToken ?>" class="butAction"><?php echo $langs->trans('AddNomenclature'); ?></a>
		</div>

		<?php
		   //$form=new Form($db);
	       //print $form->select_produits('', 'fk_clone_from_product', '', 0);
			$htmlname = 'fk_clone_from_product';
			$urloption='htmlname='.$htmlname.'&outjson=1&price_level=0&type=&mode=1&status=1&finished=2';
			print ajax_autocompleter('', $htmlname, dol_buildpath('/nomenclature/ajax/products.php', 1), $urloption, (!empty($conf->global->PRODUIT_USE_SEARCH_TO_SELECT)?$conf->global->PRODUIT_USE_SEARCH_TO_SELECT:''), 0, array());
			print $langs->trans("RefOrLabel").' : ';
			print '<input type="text" size="20" name="search_'.$htmlname.'" id="search_'.$htmlname.'" value="" '.(empty($conf->global->NOMENCLATURE_DISABLE_AUTOFOCUS) ? 'autofocus' : '').' />';
	    ?>
	    <div class="inline-block divButAction">
	        <input id="nomenclature_bt_clone_nomenclature" type="button" name="clone_nomenclature" class="butAction" value="<?php echo $langs->trans('CloneNomenclatureFromProduct'); ?>" />
	    </div>
	</div>
	<?php
    }

    if (GETPOST('optioncss', 'none') !== 'print') print '<div  class="accordion" >';
    else print '<div class="no-accordion">';
	$accordeonActiveIndex = 'false';
	$idion = 0;
	foreach($TNomenclature as $iN => &$n) {



	    // open if edited
	    $fk_nomenclature=(int)GETPOST('fk_nomenclature', 'int');

	    // default open
	    if(!empty($n->is_default) && empty($fk_nomenclature)){
	        $accordeonActiveIndex = $idion;
	    }

	    if(!empty($fk_nomenclature) && $fk_nomenclature == $n->id){ $accordeonActiveIndex = $idion; }
	    $idion++;

		// On passe par là depuis l'onglet "Ouvrage" d'un produit, du coup il faut passer la qty_reference de la nomenclature
	    _fiche_nomenclature($PDOdb, $n, $product, $object, $product->id, 'product', $n->qty_reference);
	}

	if(count($TNomenclature) === 1){ $accordeonActiveIndex = 0 ;}

	print '</div>';
	print '<script>$( function() { $( ".accordion" ).accordion({header: ".accordion-title",  collapsible: true, active:'.$accordeonActiveIndex.'}); } );</script>';
	print '<script type="text/javascript" src="'.dol_buildpath('nomenclature/js/searchproductcategory.js.php',1).'"></script>';


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
			'Id'=>'<a href="'.dol_buildpath('/nomenclature/nomenclature.php?fk_product=@val@',1).'&token=' . $newToken . '">'.img_picto($langs->trans('Nomenclature'),'object_list').' '.$langs->trans('Nomenclature').'</a>'
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

/**
 * @param int $fk_product
 * @return string
 */
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

/**
 * @param TPDOdb $PDOdb
 * @param TNomenclature $n
 * @param Product $product
 * @param CommonObject $object
 * @param int $fk_object
 * @param string $object_type
 * @param int $qty_ref
 * @param bool $readonly
 */
function _fiche_nomenclature(&$PDOdb, &$n,&$product, &$object, $fk_object=0, $object_type='product', $qty_ref=1, $readonly=false) {
	global $langs, $conf, $db, $user, $hookmanager;

	$coef_qty_price = $n->setPrice($PDOdb,$qty_ref,$fk_object,$object_type,(int)GETPOST('fk_origin', 'int'));
	$newToken = function_exists('newToken') ? newToken() : $_SESSION['newtoken'];


	print '<h3 class="accordion-title">';
	print $langs->trans('Nomenclature').' n°'.$n->getId();
	print ' '.$n->title;

	print ' - '.$langs->trans('nomenclatureQtyReference').' '. $n->qty_reference;

	$price_buy = $n->getBuyPrice(); // prix d'achat total
	print ' - '.$langs->trans('TotalAmountCostWithCharge').' '. price($price_buy);

	$price_to_sell =  $n->getSellPrice($qty_ref); // prix de vente conseillé total
	print ' - '.$langs->trans('PriceConseil').' '. price($price_to_sell*$qty_ref);
    if (GETPOST('json', 'none') == 1 && $n->non_secable) print ' ('.$langs->trans('nomenclatureNonSecableForQty', $n->qty_reference).')';

	print '</h3>';

	print '<div id="nomenclature'.$n->id.'" class="tabBar accordion-body">';

	$json = GETPOST('json', 'int');
	$form=new Form($db);

	if($n->getId() == 0 &&  count($n->TNomenclatureDet)+count($n->TNomenclatureWorkstation)>0) {
		echo '<div class="error">'.$langs->trans('NonLocalNomenclature').'</div>';
	}

	$pAction = $n->getId() ? $_SERVER['PHP_SELF'].'?fk_product='.$n->fk_object : 'auto';
    $formCore=new TFormCore($pAction, 'form_nom_'.$n->getId(), 'post', false);
    if ($readonly) {
        $formCore->Set_typeaff('view'); // $formCore methods will return read-only elements instead of form inputs
    } else {
        echo $formCore->hidden('action', 'save_nomenclature');
        echo $formCore->hidden('json', $json);
        echo $formCore->hidden('fk_nomenclature', $n->getId());
        echo $formCore->hidden('fk_product', $product->id);
        echo $formCore->hidden('fk_object', $fk_object);
        echo $formCore->hidden('object_type', $object_type);
        echo $formCore->hidden('fk_origin', GETPOST('fk_origin', 'int'));
        echo $formCore->hidden('qty_ref', $qty_ref);
        if ($json) echo $formCore->hidden('non_secable', $n->non_secable);
    }

    $TCoef = TNomenclatureCoefObject::loadCoefObject($PDOdb, $object, $object->element);
    $TCoefFinal = TNomenclatureCoef::loadCoef($PDOdb, 'pricefinal');

	?>
	<script type="text/javascript">
	$(document).ready(function() {
		$(".det-table>tbody").sortable({
			handle:".handler"
			,placeholder: "ui-state-highlight"
			,stop:function(event,ui) {
				var sorted = $(this).sortable( "toArray", { attribute: "rowid" } );

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
		$(".workstation-table>tbody").sortable({
			handle:".handler"
			,placeholder: "ui-state-highlight"
			,stop:function(event,ui) {
				var sorted = $(this).sortable( "toArray", { attribute: "rowid" } );

				$.ajax({
					url:"<?php echo dol_buildpath('/nomenclature/script/interface.php',1) ?>"
					,data:{
						put:"rang"
						,type:'ws'
						,TRank:sorted
					}
					,success: function(data) {
//console.log('Sort');
						$('.workstation-table tr[rowid]').each(function(i, elem) {
							$(elem).find('input, textarea').each(function(j, child) {
								var name = $(child).prop('name').replace(/^(TNomenclatureWorkstation\[)([0-9]+)(\].*)/, '$1'+i+'$3');
								$(child).prop('id', name).prop('name', name);
//console.log(name);
							});
						});

					}
				});
			}

		});

		<?php if(!empty($conf->global->NOMENCLATURE_ALLOW_USE_MANUAL_COEF)) { ?>
			// Récupération des coef
			TCoef = {<?php foreach($TCoef as $obj_coef) echo '"'.$obj_coef->code_type.'":'.$obj_coef->tx.','; ?>};

			$('.select_coef').change(function() {
				$(this).parent('td').find('input[type="text"]').val(TCoef[$(this).val()]);
			});
		<?php }?>

        // Récupération du coeffinal
        $('.select_coef_final').change(function() {

            var code_type = $(this).val();

            $.ajax({
                url: "<?php echo dol_buildpath('/nomenclature/script/interface.php', 1) ?>"
                , data: {
                    put: 'set-marge-final'
                    , code_type: code_type
                    , nomenclature_id: '<?php echo $n->rowid; ?>'
                }
                , dataType: "json"
            });
        });

		<?php if(!empty($conf->global->NOMENCLATURE_USE_LOSS_PERCENT)) { ?>

			$("input[name*=qty_base], input[name*=loss_percent]").change(function() {

				if(typeof $('input[name*=qty_base]').attr('name') !== 'undefined') {

					var line = $(this).closest('tr');
					var val_qty_base = $(line).find("input[name*=qty_base]").val();
					var val_loss_percent = $(line).find("input[name*=loss_percent]").val();

					var newQty = val_qty_base * ( 1 +  val_loss_percent / 100 );
					newQty = Math.round(newQty*100)/100;
					<?php if(!empty($conf->global->MAIN_MAX_DECIMALS_UNIT)) { ?>
					var maxdecimal = <?php echo $conf->global->MAIN_MAX_DECIMALS_UNIT ?>; //on arrondit la nouvelle quantité en fonction du nombre de décimal autorisé par la conf
					newQty = newQty.toFixed(maxdecimal);
					<?php } ?>
					$(this).closest('tr').find('td.ligne_col_qty').find("input[name*=qty]").val(newQty);
				}

			});

		<?php } ?>

	});
	</script>

    <table class="liste" width="100%" id="nomenclature-<?php echo $n->getId(); ?>"><?php
    	if($object_type == 'product') {
	        ?><tr class="liste_titre">
	            <td class="liste_titre"><?php echo $langs->trans('Nomenclature').' '.$langs->trans('numberShort').$n->getId(); ?></td>
	            <td class="liste_titre"><?php echo $formCore->texte($langs->trans('Title'), 'title', $n->title, 50,255); ?></td>
	            <td class="liste_titre"><?php echo $formCore->texte($langs->trans('nomenclatureQtyReference'), 'qty_reference', $n->qty_reference, 5,10); ?></td>
	            <td class="liste_titre"><?php echo $formCore->checkbox('', 'non_secable', array(1 => $langs->trans('nomenclatureNonSecable').' '.img_help(1, $langs->trans('nomenclatureNonSecableHelp'))), $n->non_secable); ?></td>
	            <td align="right" class="liste_titre"><?php echo $formCore->checkbox('', 'is_default', array(1 => $langs->trans('nomenclatureIsDefault')), $n->is_default, 'onclick="javascript:uncheckOther(this);"') ?></td>
                <td align="right" class="liste_titre"><?php if (!$readonly) { ?><a href="javascript:deleteNomenc(<?php echo $n->getId(); ?>)"><?php echo img_delete($langs->trans('DeleteThisNomenclature')) ?></a><?php } ?></td>
	        </tr><?php
        }

        ?>
        <tr>
           <td colspan="6">
               <?php

               $TNomenclatureDet = &$n->TNomenclatureDet;

               if(count($TNomenclatureDet)>0) {

                   ?>
                   <table width="100%" class="liste det-table">
                       <thead>
                       <tr class="liste_titre">
                           <th class="liste_titre col_product" width="55%"><?php echo $langs->trans('Product'); ?></th>
                           <?php if(!empty($conf->global->PRODUCT_USE_UNITS)) { ?> <th class="liste_titre col_fk_unit" width="5%"><?php echo $langs->trans('Unit'); ?></th> <?php } ?>
                           <?php
		                        if(!empty($conf->global->FOURN_PRODUCT_AVAILABILITY))
								{
									print '<th class="liste_titre" width="5%">'.$langs->trans('Availability').'</th>';
								}

								if(!empty($conf->stock->enabled) && empty($conf->global->NOMENCLATURE_HIDE_STOCK_COLUMNS)) {
								   ?>

		                           <th class="liste_titre col_physicalStock" width="5%"><?php echo $langs->trans('PhysicalStock'); ?></th>
		                           <th class="liste_titre col_virtualStock" width="5%"><?php echo $langs->trans('VirtualStock'); ?></th>
		                        <?php } ?>
		                   <?php if(!empty($conf->global->NOMENCLATURE_USE_LOSS_PERCENT)) { ?>
		                   		<th class="liste_titre col_qty_base" width="5%"><?php echo $langs->trans('qty_base'); ?></th>
		                   		<th class="liste_titre col_loss_percent" width="5%"><?php echo $langs->trans('LossPercent'); ?></th>
		                   <?php } ?>
                           <th class="liste_titre col_qty" width="5%"><?php echo $langs->trans('Qty'); ?></th>
                           <?php if(!empty($conf->global->NOMENCLATURE_USE_CUSTOM_BUYPRICE)) { ?> <th class="liste_titre col_buy_price" width="5%"><?php echo $langs->trans('BuyingPriceCustom'); ?></th> <?php } ?>
                           <?php if($user->rights->nomenclature->showPrice) { ?>
                               <th class="liste_titre col_amountCostUnit" align="right" width="5%"><?php echo $langs->trans('AmountCostUnit'); ?></th>
                               <th class="liste_titre col_amountCost" align="right" width="5%"><?php echo $langs->trans('AmountCost'); ?></th>
							   <th class="liste_titre col_type" width="5%"><?php echo $langs->trans('CoefCharge'); ?></th>

                               <th class="liste_titre col_amountCostWithChargeUnit" align="right" width="5%"><?php echo $langs->trans('AmountCostWithChargeUnit'); ?></th>
                               <th class="liste_titre col_amountCostWithCharge" align="right" width="5%"><?php echo $langs->trans('AmountCostWithCharge'); ?></th>
                               <?php if(!empty($conf->global->NOMENCLATURE_USE_COEF_ON_COUT_REVIENT)) { ?>
                           			<th class="liste_titre col_coef2" width="5%"><?php echo $langs->trans('CoefMarge'); ?></th>
                           			<th class="liste_titre col_coef2" width="5%"><?php echo $langs->trans('PV'); ?></th>
                               <?php } ?>
                               <th class="liste_titre col_amountCostWithChargeCustom" align="right" width="5%"><?php echo $langs->trans('AmountCostWithChargeCustom'); ?></th><?php
                           }
                           ?>
                           <th class="liste_titre" width="1%">&nbsp;</th>
                           <th class="liste_titre" width="1%">&nbsp;</th>
                       </tr>
                       </thead>
                       <tbody>
                       <?php


						//Chaque tableau de coef a pour key le rowid du coef

					   $total_charge =$coldisplay= 0;
                       $class='';$total_produit = $total_mo  = 0;
                       foreach($TNomenclatureDet as $k=>&$det) {

                           $class = ($class == 'impair') ? 'pair' : 'impair';

                           ?>
                           <tr class="<?php echo $class ?>" rowid="<?php echo $det->getId(); ?>" id="line_<?php echo $det->getId(); ?>">
                               <td><?php
                                    $p_nomdet = new Product($db);
                                    if ($det->fk_product>0 && $p_nomdet->fetch($det->fk_product)>0)
                                    {
                                        echo $p_nomdet->getNomUrl(1).' '.$p_nomdet->label;

                                        if($p_nomdet->load_stock() < 0) $p_nomdet->load_virtual_stock(); // TODO AA pourquoi ? load_stock le fait et s'il échoue... :/
                                    } elseif ($readonly) {
                                        echo $det->title;
                                    } else {
                                        echo '<input type="text" value="'.$det->title.'" name="TNomenclature['.$k.'][title]" />';
                                    }

                                    $sub_n = _draw_child_arbo($PDOdb, $p_nomdet->id, $det->qty);

									if ($readonly) echo '<div class="note_private">';
									echo $formCore->zonetexte('', 'TNomenclature['.$k.'][note_private]', $det->note_private, 80, 1,' style="width:95%;"');
									if ($readonly) echo '</div>';

									if(!empty($conf->global->NOMENCLATURE_ALLOW_TO_LINK_PRODUCT_TO_WORKSTATION)) {

										if(empty($TWorkstationToSelect)) {
											$TWorkstationToSelect=array();
											foreach($n->TNomenclatureWorkstation as &$wsn) {
												$TWorkstationToSelect[$wsn->workstation->id] = $wsn->workstation->name;
											}
										}

										echo $form->multiselectarray('TNomenclature_'.$k.'_workstations', $TWorkstationToSelect,(empty($det->workstations) ? array() : explode(',', $det->workstations)),0,0,'minwidth300'  );

									}

                                ?></td>

	                                <?php if(!empty($conf->global->PRODUCT_USE_UNITS)) { ?>
		                               <td class="ligne_col_fk_unit nowrap" ><?php

										   // To display warning message if product haven't the same unit as bom
										   $det->productCurrentUnit = $object->getValueFrom('c_units', $p_nomdet->fk_unit, 'label');
										   $det->warningUnitNotTheSameAsProduct = ($det->fk_unit != $p_nomdet->fk_unit);

		                               		if(!empty($conf->global->NOMENCLATURE_ALLOW_SELECT_FOR_PRODUCT_UNIT) || !empty($det->warningUnitNotTheSameAsProduct)){
		                               			echo $form->selectUnits($det->fk_unit, 'TNomenclature['.$k.'][fk_unit]', 1);
											}
		                               		else {
		                               			// On copie l'unité de la ligne dans l'objet produit pour utiliser la fonction getLabelOfUnit()
		                               			$original_fk_unit = $p_nomdet->fk_unit;
		                               			$p_nomdet->fk_unit = $det->fk_unit;
		                               			print ucfirst($langs->trans($p_nomdet->getLabelOfUnit()));
		                               			// On remet l'unité de base du produit au cas où
		                               			$p_nomdet->fk_unit = $original_fk_unit;
		                               		}

										   if($det->warningUnitNotTheSameAsProduct){
											   $unitTitle = $langs->trans('WarningUnitOfBomIsNotTheSameAsProduct', $langs->trans($det->productCurrentUnit));
											   echo '<span class="badge badge-danger classfortooltip" title="'.$unitTitle.'" ><span class="fa fa-warning"></span></span>';
										   }
									    ?></td>
									<?php }

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


								if(!empty($conf->stock->enabled) && empty($conf->global->NOMENCLATURE_HIDE_STOCK_COLUMNS)) {
	                               ?>
	                               <td>
	                               	<?php echo $det->fk_product>0 ? price($p_nomdet->stock_reel,'',0,1,1,2) : '-'; ?>
	                               </td>
	                               <td class="ligne_col_virtualStock">
	                               	<?php
	                               		if(!empty($conf->of->enabled) && $p_nomdet->id>0){

	                               			// On récupère les quantités dans les OF
	                               			$q = 'SELECT ofl.qty, ofl.qty_needed, ofl.qty, ofl.type
	                               					FROM '.MAIN_DB_PREFIX.'assetOf `of`
	                               					INNER JOIN '.MAIN_DB_PREFIX.'assetOf_line ofl ON(ofl.fk_assetOf = `of`.rowid)
	                               					WHERE fk_product = '.$p_nomdet->id.' AND `of`.status NOT IN("DRAFT","CLOSE")';
		                               		$resql = $db->query($q);

		                               		if($resql){
												// On régule le stock théorique en fonction de ces quantités
												while($res = $db->fetch_object($resql)) {
													if($res->type === 'TO_MAKE') $p_nomdet->stock_theorique += $res->qty; // Pour les TO_MAKE la bonne qté est dans le champ qty
													elseif($res->type === 'NEEDED') $p_nomdet->stock_theorique -= empty($res->qty_needed) ? $res->qty : $res->qty_needed;
												}
											}
		                               		else{
		                               			print $db->lasterror();
											}

										}
	                               		echo !empty($det->fk_product) ? price($p_nomdet->stock_theorique,'',0,1,1,2) : '-';
	                               	?>
	                               </td>
	                             <?php }

	                             	if(!empty($conf->global->NOMENCLATURE_USE_LOSS_PERCENT)) {
	                             		echo '<td class="ligne_col_qty_base" nowrap>'.$formCore->texte('', 'TNomenclature['.$k.'][qty_base]', $det->qty_base, 2,100, '').'</td>';
	                             		echo '<td nowrap>'.$formCore->texte('', 'TNomenclature['.$k.'][loss_percent]', $det->loss_percent, 2,100).'%</td>';
	                             	}
	                           ?>

                               <td class="ligne_col_qty"><?php
                               echo $formCore->texte('', 'TNomenclature['.$k.'][qty]', $det->qty, 7,100);
							   		if($coef_qty_price != 1) echo '<br /> x '.price($coef_qty_price,'','',2,2) ;
							    ?></td>
								<?php

							   if(!empty($conf->global->NOMENCLATURE_USE_CUSTOM_BUYPRICE)) {
							       $c = new TNomenclature();
                                    //Conf active and Product has a nomenclature
							       if ($conf->global->NOMENCLATURE_TAKE_PRICE_FROM_CHILD_FIRST && $c->loadByObjectId($PDOdb, $n->TNomenclatureDet[$k]->fk_product, 'product')){
                                    echo '<td nowrap>';
                                    echo $formCore->texte('', 'TNomenclature['.$k.'][buying_price]',empty($det->buying_price) ? '' : $det->buying_price, 7,100);
                                    echo '</td>';
                                    }
							       else{
                                    ?><td nowrap>
                                    <select id="TNomenclature[<?php echo $k; ?>][fk_fournprice]" name="TNomenclature[<?php echo $k; ?>][fk_fournprice]" class="flat"></select><?php
                                    echo $formCore->texte('', 'TNomenclature['.$k.'][buying_price]', empty($det->buying_price) ? '' : $det->buying_price, 7,100);
                                    echo '</td>';
                                    $det->printSelectProductFournisseurPrice($k, $n->rowid, $n->object_type);
							       }

							   }
	                            if($user->rights->nomenclature->showPrice) {
	                            	$price = $det->calculate_price; //Si on arrondit cette valeur l'affichage de la colonne prix d'achat unitaire est fausse
									$price_charge = $det->charged_price;


                                    print '<td class="col_amountCostUnit"  >';
                                    if(!empty($det->qty)){
                                        if (
                                            !empty($conf->global->NOMENCLATURE_APPLY_FULL_COST_NON_SECABLE)
                                            && is_object($sub_n)
                                            && $sub_n->non_secable
                                        )
                                        {
                                            echo price(round($price / $sub_n->qty_reference, 2));
                                        }
                                        else echo price(round($price / $det->qty, 2));
                                    }
                                    print '</td>';


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

                               }
                               ?>

								   <td nowrap><?php echo $formCore->combo('', 'TNomenclature['.$k.'][code_type]', TNomenclatureDet::getTType($PDOdb), $det->code_type, 1, '', '', 'select_coef'); ?>

	                               <?php if(!empty($conf->global->NOMENCLATURE_ALLOW_USE_MANUAL_COEF)) {
	                               		echo $formCore->texte('', 'TNomenclature['.$k.'][tx_custom]', empty($det->tx_custom) ? $TCoef[$det->code_type]->tx : $det->tx_custom, 3,100);
	                               } ?>

	                               </td>

								   <?php



                                   print '<td class="col_amountCostWithChargeUnit"  >';
                                   echo $det->qty>0?price(round($price_charge/$det->qty, 2)) : '';
                                   print '</td>';

									echo '<td class="col_amountCostWithCharge" align="right" valign="middle">';
									if(!empty($conf->global->NOMENCLATURE_ACTIVATE_DETAILS_COSTS)) {
										echo price($price_charge);
										echo '<span class="pricePMP"><br />'.price(price2num($det->charged_price_pmp,'MT')).'</span>';
										if(!empty($conf->of->enabled)) echo '<span class="priceOF"><br />'.price(price2num($det->charged_price_of,'MT')).'</span>';
									}
									else{
                                    	echo price($price_charge);
									}
                                	echo '</td>';

	                            	if(!empty($conf->global->NOMENCLATURE_USE_COEF_ON_COUT_REVIENT)) { ?>

										<td nowrap><?php echo $formCore->combo('', 'TNomenclature['.$k.'][code_type2]', TNomenclatureDet::getTType($PDOdb), $det->code_type2, 1, '', '', 'select_coef'); ?>

			                               <?php if(!empty($conf->global->NOMENCLATURE_ALLOW_USE_MANUAL_COEF)) {
			                               		echo $formCore->texte('', 'TNomenclature['.$k.'][tx_custom2]', empty($det->tx_custom2) ? $TCoef[$det->code_type2]->tx : $det->tx_custom2, 3,100);
			                               } ?>

		                               	</td>
		                               	<?php

		                               		$pv = price2num($det->pv,'MT');

											echo '<td align="right" valign="middle">';
											if(!empty($conf->global->NOMENCLATURE_ACTIVATE_DETAILS_COSTS)) {
												echo price( $pv).img_help(1,$langs->trans('PricePA'));
												echo '<span class="pricePMP"><br />'.price(price2num($det->pv_pmp,'MT')).img_help(1,$langs->trans('PricePMP')).'</span>';
												if(!empty($conf->of->enabled)) echo '<span class="priceOF"><br />'.price(price2num($det->pv_of,'MT')).img_help(1,$langs->trans('PriceOF')).'</span>';
											}
											else{
												echo price($pv);
											}
											echo '</td>';


                                			echo '</td>';
	                            	}
									echo '<td align="right" valign="middle">';
                                    if ($readonly) {
                                        echo price($det->price);
                                    } else {
                                        echo '<input style="text-align:right;" name="TNomenclature[' . $k . '][price]" value="' . price($det->price) . '" size="5" />';
                                    }
                                	echo '</td>';
                               ?>

								<td><?php
								if($n->getId()>0)
								{
									$param = '?action=delete_nomenclature_detail&k='.$k.'&fk_nomenclature='.$n->getId().'&fk_product='.$product->id.'&fk_object='.$fk_object;
									$param.= '&object_type='.$object_type.'&qty_ref='.$qty_ref.'&fk_origin='.GETPOST('fk_origin', 'int').'&json='.$json . '&token='. $newToken ;

									// Si la nomenclature a été enregistré puis que les lignes ont été delete, alors l'icone de suppression ne doit pas s'afficher car ce sont les lignes chargé depuis le load_original()
									if (! empty($n->iExist) && !$readonly) echo '<a href="'.dol_buildpath('/nomenclature/nomenclature.php',1).$param.'" class="tojs">'.img_delete().'</a>';
								}
								?></td>


                               <td align="center" class="linecolmove tdlineupdown">
                                   <?php

                                   $coldisplay++;
                                   if(empty($det->id)) $det->id = 0;
                                   if(!$readonly) { ?>
                                        <a class="lineupdown handler" href="<?php echo $_SERVER["PHP_SELF"].'?fk_product='.$product->id.'&action=up&rowid='.$det->id; ?>&token=<?php echo $newToken; ?>">
                                        <?php echo img_picto('Move','grip'); ?>
                                        </a>
                                   <?php } ?>
								</td>
                           </tr>
                           <?php

                       }

					?></tbody><tfoot><?php

				       if($user->rights->nomenclature->showPrice) {
				       		$colspan = 3;
							if(!empty($conf->global->FOURN_PRODUCT_AVAILABILITY) && $conf->global->FOURN_PRODUCT_AVAILABILITY > 0) $colspan ++;
							if(empty($conf->stock->enabled)) $colspan -= 2;
							if(!empty($conf->global->PRODUCT_USE_UNITS)) $colspan ++;
							if(!empty($conf->global->NOMENCLATURE_USE_LOSS_PERCENT)) $colspan += 2;
							if(!empty($conf->global->NOMENCLATURE_USE_CUSTOM_BUYPRICE)) $colspan ++;
			?>
                       <tr class="liste_total">
                           <td ><?php echo $langs->trans('Total'); ?></td>
                           <td class="total_colspan" colspan="<?php echo $colspan; ?>">&nbsp;</td>
                           <td class="col_amountCostUnit" align="right"></td>
                           <td align="right"><?php echo price(price2num($n->totalPR,'MT')); ?></td>
                           <td align="right"></td>

                           <td class="col_amountCostWithChargeUnit" align="right"></td>
                           <td align="right"><?php echo price(price2num($n->totalPRC,'MT')); ?></td>
                           <?php if(!empty($conf->global->NOMENCLATURE_USE_COEF_ON_COUT_REVIENT)) {
                           			print '<td align="right"></td>';
                           			?><td align="right"><?php echo price(price2num($n->getSellPrice($qty_ref)*$qty_ref,'MT')); ?></td><?php
                           		  } ?>
                           <td align="right"><?php /*echo price(round($total_produit_coef_final,2));*/ ?></td>
                           <td align="right"></td>
                           <td align="right"></td>
                       </tr>
                       <?php

                       if(!empty($conf->global->NOMENCLATURE_ACTIVATE_DETAILS_COSTS)) {
	                        ?>
	                       <tr class="liste_total">
	                           <td ><?php echo $langs->trans('TotalPricePMP'); ?></td>
	                           <td class="total_colspan" colspan="<?php echo $colspan; ?>">&nbsp;</td>
                               <td class="col_amountCostUnit" align="right"></td>
	                           <td align="right"><?php echo price(price2num($n->totalPR_PMP,'MT')); ?></td>
	                           <td align="right"></td>
                               <td class="col_amountCostUnit" align="right"></td>
	                           <td align="right"><?php echo price(price2num($n->totalPRC_PMP,'MT')); ?></td>
	                           <?php if(!empty($conf->global->NOMENCLATURE_USE_COEF_ON_COUT_REVIENT)) {
	                           			print '<td align="right"></td>';
	                           			?><td align="right"><?php echo price(price2num($n->totalPV_PMP,'MT')); ?></td><?php
	                           		  } ?>
	                           <td align="right"><?php /*echo price(round($total_produit_coef_final,2));*/ ?></td>
	                           <td align="right"></td>
	                           <td align="right"></td>

	                       </tr><?php

	                       if(!empty($conf->of->enabled)) {

		                       ?><tr class="liste_total">
		                           <td ><?php echo $langs->trans('TotalPriceOF'); ?></td>
		                           <td colspan="<?php echo $colspan; ?>">&nbsp;</td>
                                   <td class="col_amountCostUnit" align="right"></td>
		                           <td align="right"><?php echo price(price2num($n->totalPR_OF,'MT')); ?></td>
		                           <td align="right"></td>
                                   <td class="col_amountCostUnit" align="right"></td>
		                           <td align="right"><?php echo price(price2num($n->totalPRC_OF,'MT')); ?></td>
		                           <?php if(!empty($conf->global->NOMENCLATURE_USE_COEF_ON_COUT_REVIENT)) {
		                           			print '<td align="right"></td>';
		                           			?><td align="right"><?php echo price(price2num($n->totalPV_OF,'MT')); ?></td><?php
	                       				  } ?>
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

        <?php if (GETPOST('optioncss', 'none') !== 'print') { ?>
        <tr>
			<td colspan="6">
                <?php if(!$readonly) { ?>
                    <div class="tabsAction">
                        <div>
                            <?php
                            print '<label>' .$langs->trans('Qty'). '<input type="text" value="1" name="fk_new_product_qty_'.$n->getId().'" size="4" maxlength="50"/></label>';
                            print '<label>'.$langs->trans('Product').'';
							$finished = 2;
                            if(!empty($conf->global->NOMENCLATURE_ALLOW_JUST_MP)) {
								$finished = 0;
							}
							print $form->select_produits('', 'fk_new_product_'.$n->getId(), '', 0,0,-1,$finished);
							print '</label>';
                            ?>
                            <span id="nomenclature-searchbycat-<?php echo $n->getId(); ?>" class="nomenclature-searchbycat" data-nomenclature="<?php echo $n->getId(); ?>"  ></span>
                            <div class="inline-block divButAction">
                                <input id="nomenclature_bt_add_product" type="submit" name="add_nomenclature" class="butAction nomenclature_bt_add_product" value="<?php echo $langs->trans('AddProductNomenclature'); ?>" />
                                <input type="submit" name="save_nomenclature" class="butAction" value="<?php echo $langs->trans('SaveNomenclature'); ?>" />
                            </div>
                        </div>
                    </div>
                <?php } ?>
			</td>
        </tr>
        <?php } ?>

        <?php
       if(!empty($conf->workstationatm->enabled)) {

       ?>
        <tr>
           <td colspan="6"><?php
               ?>
               <table class="liste workstation-table" width="100%">
               	<thead>
               <tr class="liste_titre">
                   <!--<th class="liste_titre"><?php echo $langs->trans('Type'); ?></th>-->
                   <th class="liste_titre" colspan="1" width="55%"><?php echo $langs->trans('Workstation'); ?></th>
                   <th class="liste_titre" colspan="1" width="5%"></th>
                   <?php if (!empty($conf->global->NOMENCLATURE_USE_TIME_BEFORE_LAUNCH)) {?>
                   <th class="liste_titre" width="5%"><?php echo $langs->trans('nb_days_before_beginning').img_info($langs->trans('nb_days_before_beginningHelp')); ?></th>
                   <?php }?>
                   <?php if (!empty($conf->global->NOMENCLATURE_USE_TIME_PREPARE)) {?>
                   <th class="liste_titre" width="5%"><?php echo $langs->trans('QtyPrepare').img_info($langs->trans('QtyPrepareHelp')); ?></th>
                   <?php }?>
                   <?php if (!empty($conf->global->NOMENCLATURE_USE_TIME_DOING)) {?>
                   <th class="liste_titre" width="5%"><?php echo $langs->trans('QtyFabrication').img_info($langs->trans('QtyFabricationHelp')); ?></th>
                   <?php }?>
                   <th class="liste_titre" width="5%"><?php echo $langs->trans('Qty'); ?></th>
                   <th class="liste_titre" width="5%"><?php echo $langs->trans('Type'); ?></th>
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
//var_dump($ws);exit;
//					   var_dump($ws->getId(), $ws);
                       $class = ($class == 'impair') ? 'pair' : 'impair';
                       /*
					    * <!-- Pas sur la MO	<td><?php
                       		echo $formCore->combo('', 'TNomenclatureWorkstation['.$k.'][code_type]', TNomenclatureDet::getTType($PDOdb), $ws->code_type);
                       	?></td> -->
                        */
                       ?>
                       <tr class="<?php echo $class ?>"  id="nomenclature-ws-item-k-<?php echo $k; ?>" rowid="<?php echo $ws->getId(); ?>">
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
                           <td>
                           	<?php echo $formCore->combo('', 'TNomenclatureWorkstation['.$k.'][code_type]', TNomenclatureDet::getTType($PDOdb, false, 'workstation'), $ws->code_type, 1, '', '', 'select_coef'); ?>
                           </td>

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
								$param.= '&object_type='.$object_type.'&qty_ref='.$qty_ref.'&fk_origin='.GETPOST('fk_origin', 'int').'&json='.$json.'&token='.$newToken;

								// Si la nomenclature a été enregistré puis que les lignes ont été delete, alors l'icone de suppression ne doit pas s'afficher car ce sont les lignes chargé depuis le load_original()
								if (! empty($n->iExist) && !$readonly) echo '<a href="'.dol_buildpath('/nomenclature/nomenclature.php',1).$param.'" class="tojs">'.img_delete().'</a>';
							}
							?>
						</td>

                               <td align="center" class="linecolmove tdlineupdown"><?php $coldisplay++; ?>
									<a class="lineupdown handler" href="<?php echo $_SERVER["PHP_SELF"].'?fk_product='.$product->id.'&amp;action=up&amp;rowid='.$ws->id; ?>&token=<?php echo $newToken; ?>">
									<?php echo img_picto('Move','grip'); ?>
									</a>
								</td>
                       </tr>

                       <?php


                   }

				?></tbody><tfoot><?php

					$colspan = 5;
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
        </tr>
        <?php
            if (GETPOST('optioncss', 'none') !== 'print') {
                ?>
                <tr>
                    <td align="right" colspan="6">
                        <div class="tabsAction">
                            <div>
                                <?php
                                if ( $conf->workstationatm->enabled && ! $readonly ) {
                                    echo $formCore->combo( '', 'fk_new_workstation', TWorkstation::getWorstations( $PDOdb, false, ! empty( $conf->global->NOMENCLATURE_PRESELECT_FIRST_WS ) ? false : true ), - 1 );
                                    ?>
                                    <div class="inline-block divButAction">
                                        <input type="submit" name="add_workstation" class="butAction"
                                               value="<?php echo $langs->trans( 'AddWorkstation' ); ?>"/>
                                        <input type="submit" name="save_nomenclature" class="butAction"
                                               value="<?php echo $langs->trans( 'SaveNomenclature' ); ?>"/>
                                    </div>
                                    <?php
                                } // end if
                                ?>
                            </div>
                        </div>
                    </td>
                </tr>
                <?php
        	}
        }

		if($user->rights->nomenclature->showPrice)
		{
				// La methode setPrice garde maintenant l'objet marge dans un attribut, pas besoin de le reload
				// pour rien surtout qu'une commande peut avoir une propal d'origine qui possède des coef custom
				$marge = $n->marge_object;
                if(empty($marge)) $marge = new stdClass();

				$PR = price2num($n->totalPR,'MT');
				$PR_coef = price2num($n->totalPRCMO,'MT'); // Prix de revient chargé (on affiche tjr le chargé)
				$price_buy = $n->getBuyPrice(); // prix d'achat total
				$price_to_sell =  $n->getSellPrice($qty_ref); // prix de vente conseillé total
		        ?>
            <tr  data-row="TotalRow" >

            </tr>

                <table class="liste det-table" style="width: 100%" >
                    <thead>
                    <tr class="liste_total" >
                        <th ></th>
                        <?php if($qty_ref!=1 && !empty($qty_ref)) { ?>
                        <th style="text-align: right;" ><?php echo $langs->trans('TotalUnit'); ?></th>
                        <?php } ?>
                        <th style="text-align: right;" ><?php echo $langs->trans('TotalForX', $qty_ref); ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr class="liste_total" data-row="TotalAmountCost" >
                        <td style="font-weight: bolder;"><?php echo $langs->trans('TotalAmountCost'); ?></td>

                        <?php if($qty_ref!=1 && !empty($qty_ref)) { ?>
                            <td style="font-weight: bolder; text-align: right;"><?php echo price($PR/ (!empty($qty_ref)?$qty_ref:1)); ?></td>
                        <?php } ?>

                        <td style="font-weight: bolder; text-align: right;"><?php echo price($PR); ?></td>
                    </tr>


                    <tr class="liste_total" data-row="TotalAmountCostWithCharge" >
                        <td style="font-weight: bolder;"><?php echo $langs->trans('TotalAmountCostWithCharge'); ?></td>

                        <?php if($qty_ref!=1 && !empty($qty_ref)) { ?>
                            <td style="font-weight: bolder; text-align: right;"><?php echo price($PR_coef/$qty_ref); ?></td>
                        <?php } ?>

                        <td style="font-weight: bolder; text-align: right;"><?php echo price($PR_coef); ?></td>
                        <?php echo $formCore->hidden('price_buy', round($price_buy,2)); ?>
                    </tr>


                <?php
		       if(!empty($conf->global->NOMENCLATURE_ACTIVATE_DETAILS_COSTS)) { ?>

                   <tr class="liste_total" data-row="TotalAmountCostWithChargePMP" >
                       <td style="font-weight: bolder;"><?php echo $langs->trans('TotalAmountCostWithChargePMP'); ?></td>
                       <?php if($qty_ref!=1 && !empty($qty_ref)) { ?>
                           <td style="font-weight: bolder; text-align: right;"><?php echo price(price2num($n->totalPRCMO_PMP/$qty_ref,'MT')); ?></td>
                       <?php } ?>
                       <td style="font-weight: bolder; text-align: right;"><span class="pricePMP"><?php echo price(price2num($n->totalPRCMO_PMP,'MT')); ?></span></td>
                   </tr>

                   <tr class="liste_total" data-row="TotalAmountCostWithChargeOF" >
                       <td style="font-weight: bolder;"><?php echo $langs->trans('TotalAmountCostWithChargeOF'); ?></td>
                       <?php if($qty_ref!=1 && !empty($qty_ref)) { ?>
                           <td style="font-weight: bolder; text-align: right;"><?php echo price(price2num($n->totalPRCMO_OF/$qty_ref,'MT')); ?></td>
                       <?php } ?>
                       <td style="font-weight: bolder; text-align: right;"><span class="priceOF"><?php echo price(price2num($n->totalPRCMO_OF,'MT')); ?></span></td>
                   </tr>

	              	    <?php
		        }

		        if(!empty($conf->global->NOMENCLATURE_HIDE_ADVISED_PRICE))
				{
					echo $formCore->hidden('price_to_sell', $price_to_sell);
				}
				else {
				if(empty($marge->tx_object)) $marge->tx_object = 0;
				if(empty($marge->tx_object)) $marge->tx_object = 0;
		        ?>



                    <tr class="liste_total"  data-row="PriceConseil" >
                        <td style="font-weight: bolder;"><?php echo $langs->trans('PriceConseil', ($marge->tx_object -1)* 100); ?>
                            <?php
                            $TCoeffinaltoselect = array();
                            foreach($TCoefFinal as $coef){
                                $TCoeffinaltoselect[$coef->code_type] = $coef->label;
                            }
                            echo $form->selectarray('select_coef_final', $TCoeffinaltoselect, $n->marge_object, '', '', '', '', '','','','');
                            echo img_help('', $langs->trans('NomenclatureHelpMargePriceFinal'));
                            ?>
                        </td>
                        <?php if($qty_ref!=1 && !empty($qty_ref)) { ?>
                            <td style="font-weight: bolder; text-align: right;"><?php echo price($price_to_sell); ?></td>
                        <?php } ?>

                        <td style="font-weight: bolder; text-align: right;">
                            <?php echo price($price_to_sell*$qty_ref); ?>
                            <?php echo $formCore->hidden('price_to_sell', $price_to_sell); ?>
                        </td>
                    </tr>


		        <?php
		        }

				print '</tbody></table>';
		}

		if (GETPOST('optioncss', 'none') !== 'print')
        {
//		?><!--<tr>-->
<!--            <td align="right" colspan="5">-->
<!--                <div class="tabsAction">-->
<!--                    --><?php
//
//                    if($conf->workstationatm->enabled && !$readonly) {
//
//                           echo $formCore->combo('', 'fk_new_workstation',TWorkstation::getWorstations($PDOdb, false, !empty($conf->global->NOMENCLATURE_PRESELECT_FIRST_WS) ? false : true), -1);
//                        ?>
<!--                        <div class="inline-block divButAction">-->
<!--                        <input type="submit" name="add_workstation" class="butAction" value="--><?php //echo $langs->trans('AddWorkstation'); ?><!--" />-->
<!--                        </div>-->
<!--                        --><?php
//                    }
//
//                    ?>
<!--                </div>-->
<!--            </td>-->
<!--        </tr>-->

        <tr>
			<td colspan="5">
				<div class="tabsAction">
					<?php
                    if (!$readonly) {
                        if ($json == 1) { ?>
                            <style type="text/css">
                                .dialogSouldBeZindexed {
                                    z-index: 1500 !important; /* 101 Ce z-index avait été ajouté pour un problème de superposition avec les select produits contenu dans la fenêtre mais apparemment on en a plus besoin */
                                    /* => finalement je le remet car je rencontre de nouveau le problème et je le reproduit à chaque fois que je fait plusieurs recherche via les selects (inputs)
                                    Avec la v8 de dolibarr le menu du haut passe devant le bouton close de la boite de dialogue (plus possibl ede fermer), je passe le z-index de 101 à 210
                                    Note : le changement pourrait avoir un impacte sur les menu déroulants, j'ai fait un test j'aipas eu de soucis,
                                    si tel est le cas alors voir si on peut corriger les z-index des autres popup
                                    */
                                    overflow: visible !important; /* Permet de ne pas tronquer le visuel après un ajout */
                                }

								/* le select2 d'ajout de produit passe derrière la popin... impossible d'ajouter un produit à une nomenclature de ligne. C'est un peu chiant. */
								.select2-dropdown{
									z-index: 2000;
								}

                            </style>
                            <div>
                                <?php
                                //$form=new Form($db);
                                //print $form->select_produits('', 'fk_clone_from_product', $sql, 0);*/

                                $htmlname = 'fk_clone_from_product';
                                $urloption = 'htmlname=' . $htmlname . '&outjson=1&price_level=0&type=&mode=1&status=1&finished=2';
                                print ajax_autocompleter('', $htmlname, dol_buildpath('/nomenclature/ajax/products.php', 1), $urloption, !empty($conf->global->PRODUIT_USE_SEARCH_TO_SELECT) ? $conf->global->PRODUIT_USE_SEARCH_TO_SELECT : '', 0, array());
                                print $langs->trans("RefOrLabel") . ' : ';
                                print '<input type="text" size="20" name="search_' . $htmlname . '" id="search_' . $htmlname . '" value="" ' . (empty($conf->global->NOMENCLATURE_DISABLE_AUTOFOCUS) ? 'autofocus' : '') . ' />';

                                ?>
                                <div class="inline-block divButAction">
                                    <input id="nomenclature_bt_clone_nomenclature" type="submit"
                                           name="clone_nomenclature" class="butAction"
                                           value="<?php echo $langs->trans('CloneNomenclatureFromProduct'); ?>"/>
                                </div>
                            </div>

                        <?php }

                        if (!$json && !empty($conf->stock->enabled) && !empty($conf->global->NOMENCLATURE_ALLOW_MVT_STOCK_FROM_NOMEN)) {
                            print '<div class="inline-block divButAction">';
                            print '<a id="nomenclaturecreateqty-' . $n->getId() . '" class="butAction" href="' . dol_buildpath('/nomenclature/nomenclature.php', 1) . '?fk_product=' . $product->id . '&fk_nomenclature_used=' . $n->getId() . '&qty_reference=' . $n->qty_reference . '&action=create_stock&token='.$newToken.'">' . $langs->trans('NomenclatureCreateXQty') . '</a>';
                            print '</div>';
                        }
                        ?>

                        <div class="inline-block divButAction">
                            <input type="submit" name="save_nomenclature" class="butAction"
                                   value="<?php echo $langs->trans('SaveNomenclature'); ?>"/>
                        </div>
                        <?php if ($json) { ?>
                            <div class="inline-block divButAction">
                                <input type="submit" name="apply_nomenclature_price" class="butAction" value="<?php echo $langs->trans('ApplyNomenclaturePrice'); ?>" />
                            </div>
                        <?php }
                    }
                    $parameters = array('readonly' => $readonly);
                    $reshook = $hookmanager->executeHooks('addMoreActionsButtons', $parameters, $n, $action);
                    ?>
				</div>
			</td>
        </tr>
        <?php } ?>
    </table>
    <?php

    $formCore->end();
    print '</div>';

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
    global $db,$langs,$conf;

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

    if ($n->non_secable) print ' <i class="fas fa-unlink" title="'.$langs->trans('nomenclatureNonSecable').'"></i>';

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

	return $n;
}
