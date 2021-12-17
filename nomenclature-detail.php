<?php

require 'config.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/propal.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/order.lib.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/comm/propal/class/propal.class.php';
require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.product.class.php';
dol_include_once('/nomenclature/class/nomenclature.class.php');
dol_include_once('/nomenclature/lib/nomenclature.lib.php');
if(! class_exists('TSubtotal')) dol_include_once('/subtotal/class/subtotal.class.php');

$langs->load('nomenclature@nomenclature');
$langs->load('workstationatm@workstationatm');

$object_type = GETPOST('object', 'alpha');
$id = GETPOST('id', 'int');
$ref = GETPOST('ref', 'alpha');
$action = GETPOST('action', 'none', 2); // Check only $_POST

$hookmanager->initHooks(array('nomenclatureproductservicelist'));

if(GETPOST('save', 'none') == 'ok') setEventMessage($langs->trans('Saved'));

$form = new Form($db);
$PDOdb = new TPDOdb;

/*
 * Action
 */
if($action == 'save') {
    $TNomenclature = GETPOST('TNomenclature', 'array');
    if(! empty($TNomenclature)) {
        $TKey = array_keys($TNomenclature);
        $fk_productToEdit = $TKey[0];
        $TValue = array_shift($TNomenclature);

        // Update price of every product of Nomenclature in the proposal/order
        $className = ucfirst($object_type);
        $object = new $className($db);  // 'Propal' or 'Commande'
        $object->fetch($id);

        $nbUpdate = 0;
        foreach($object->lines as $line) {
            if(TSubtotal::isModSubtotalLine($line)) continue;

            $n = new TNomenclature;
            $n->loadByObjectId($PDOdb, $line->id, $object_type, true, $line->fk_product, $line->qty, $object->id);

            if($n->rowid == 0 && (count($n->TNomenclatureDet) + count($n->TNomenclatureWorkstation)) > 0) {
                // Charger une nomenclature en local ça peut aider des fois !
                if(!$n->iExist) $n->reinit();
                $n->object_type = $object_type;
                $n->fk_object = $line->id;

                $n->setPrice($PDOdb, $line->qty, null, $object_type, $object->id);
                $n->save($PDOdb);
            }

            $n->fetchCombinedDetails($PDOdb);

            foreach($n->TNomenclatureDetCombined as $fk_product => $det) {
                if($fk_productToEdit != $fk_product) continue;

                if($det->buying_price != intval($TValue['buying_price'])) {
                    $det->buying_price = intval($TValue['buying_price']);
                    $det->save($PDOdb);

                    $nbUpdate++;
                }
            }

            $n->save($PDOdb);
            $n->setPrice($PDOdb, $line->qty, null, $object_type, $object->id);

            _updateObjectLine($n, $object_type, $line->id, $object->id, true);
        }

        if(! empty($nbUpdate)) {
            if($nbUpdate > 1) $output = $langs->trans('NomenclatureNBSaved', $nbUpdate);
            else if($nbUpdate == 1) $output = $langs->trans('NomenclatureSaved');

            setEventMessage($output);
        }
    }

    $url = $_SERVER['PHP_SELF'];
    $url.= '?id='.$id;
    $url.= '&object='.$object_type;
    header('Location: '.$url);
    exit;
}

/*
 * View
 */
if($object_type == 'propal') {
    dol_include_once('/comm/propal/class/propal.class.php');
    $object = new Propal($db);
    $object->fetch($id, $ref);
    $object->fetch_thirdparty();

    $head = propal_prepare_head($object);
    $title = $langs->trans('Proposal');
    $picto = 'propal';

    $linkback = '<a href="'.DOL_URL_ROOT.'/comm/propal/list.php?restore_lastsearch_values=1'.(! empty($socid) ? '&socid='.$socid : '').'">'.$langs->trans("BackToList").'</a>';

    $morehtmlref = '<div class="refidno">';
    // Ref customer
    $morehtmlref .= $form->editfieldkey("RefCustomer", 'ref_client', $object->ref_client, $object, $usercancreate, 'string', '', 0, 1);
    // Thirdparty
    $morehtmlref .= '<br>'.$langs->trans('ThirdParty').' : '.$object->thirdparty->getNomUrl(1, 'customer');

    if(empty($conf->global->MAIN_DISABLE_OTHER_LINK) && $object->thirdparty->id > 0) {
        $morehtmlref .= ' (<a href="'.DOL_URL_ROOT.'/comm/propal/list.php?socid='.$object->thirdparty->id.'&search_societe='.urlencode($object->thirdparty->name).'">'.$langs->trans("OtherProposals").'</a>)';
    }

    // Project
    if(! empty($conf->projet->enabled)) {
        $langs->load("projects");
        $morehtmlref .= '<br>'.$langs->trans('Project').' : ';

        if(! empty($object->fk_project)) {
            $proj = new Project($db);
            $proj->fetch($object->fk_project);

            $morehtmlref .= '<a href="'.DOL_URL_ROOT.'/projet/card.php?id='.$object->fk_project.'" title="'.$langs->trans('ShowProject').'">';
            $morehtmlref .= $proj->ref;
            $morehtmlref .= '</a>';
        }
        else $morehtmlref .= '';
    }
    $morehtmlref .= '</div>';
}
else if($object_type == 'commande') {
    dol_include_once('/commande/class/commande.class.php');
    $object = new Commande($db);
    $object->fetch($id, $ref);
    $object->fetch_thirdparty();

    $head = commande_prepare_head($object);
    $title = $langs->trans('CustomerOrder');
    $picto = 'order';

    $linkback = '<a href="'.DOL_URL_ROOT.'/commande/list.php?restore_lastsearch_values=1'.(! empty($socid) ? '&socid='.$socid : '').'">'.$langs->trans("BackToList").'</a>';

    $morehtmlref = '<div class="refidno">';
    // Ref customer
    $morehtmlref .= $form->editfieldkey("RefCustomer", 'ref_client', $object->ref_client, $object, $user->rights->commande->creer, 'string', '', 0, 1);
    $morehtmlref .= $form->editfieldval("RefCustomer", 'ref_client', $object->ref_client, $object, $user->rights->commande->creer, 'string', '', null, null, '', 1);
    // Thirdparty
    $morehtmlref .= '<br>'.$langs->trans('ThirdParty').' : '.$object->thirdparty->getNomUrl(1);

    if(empty($conf->global->MAIN_DISABLE_OTHER_LINK) && $object->thirdparty->id > 0) {
        $morehtmlref .= ' (<a href="'.DOL_URL_ROOT.'/commande/list.php?socid='.$object->thirdparty->id.'&search_societe='.urlencode($object->thirdparty->name).'">'.$langs->trans("OtherOrders").'</a>)';
    }

    // Project
    if(! empty($conf->projet->enabled)) {
        $langs->load("projects");
        $morehtmlref .= '<br>'.$langs->trans('Project').' : ';

        if(! empty($object->fk_project)) {
            $proj = new Project($db);
            $proj->fetch($object->fk_project);
            $morehtmlref .= '<a href="'.DOL_URL_ROOT.'/projet/card.php?id='.$object->fk_project.'" title="'.$langs->trans('ShowProject').'">';
            $morehtmlref .= $proj->ref;
            $morehtmlref .= '</a>';
        }
        else $morehtmlref .= '';
    }
    $morehtmlref .= '</div>';
}
else {
    exit('? object type ?');
}

if(empty($object)) exit;

$TProductAlreadyInPage = array();
llxHeader('', 'Nomenclatures', '', '', 0, 0, array('/nomenclature/js/speed.js', '/nomenclature/js/jquery-sortable-lists.min.js'), array('/nomenclature/css/speed.css'));

dol_fiche_head($head, 'nomenclature', $title, -1, $picto);

dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref, '&object='.$object_type);

print_barre_liste($langs->transnoentities('ListRequired'), 0, $_SERVER['PHP_SELF']);

list($TProduct, $TWorkstation) = _getDetails($object, $object_type);
print_table($TProduct, $TWorkstation, $object_type);

dol_fiche_end();
llxFooter();


function getUnits(){
    global $langs,$db;
    $TUnits = array();
    $langs->load('products');

    $sql = 'SELECT rowid, label, code from '.MAIN_DB_PREFIX.'c_units';
    $sql.= ' WHERE active > 0';

    $resql = $db->query($sql);

    if ($resql){
        while($obj = $db->fetch_object($resql))
        {
            $unitLabel = $obj->label;

            $TUnits[$obj->rowid] = strtolower($unitLabel);
        }
    }
    return $TUnits;
}

function _getDetails(&$object, $object_type) {
    global $db, $PDOdb, $conf, $langs;
    dol_include_once('/subtotal/class/subtotal.class.php');

    $TProduct = array();
    $TWorkstation = array();
    $TUnits = array();
	$alreadySearched = false;
	$TUnits = getUnits();

    foreach($object->lines as $k => &$line) {
        if(empty($conf->global->NOMENCLATURE_DETAILS_TAB_REWRITE)) {
            if($line->product_type == 9) continue;

            $nomenclature = new TNomenclature;
            $nomenclature->loadByObjectId($PDOdb, $line->id, $object_type, true, $line->fk_product, $line->qty);

            $nomenclature->fetchCombinedDetails($PDOdb);


            foreach($nomenclature->TNomenclatureDetCombined as $fk_product => $det) {

                if(! isset($TProduct[$fk_product])) {
                    $TProduct[$fk_product] = $det;
                }
                else {
                    $TProduct[$fk_product]->qty += $det->qty;
                }
            }

			if (!empty($conf->global->NOMENCLATURE_INCLUDE_PRODUCTS_WITHOUT_NOMENCLATURE) && empty($nomenclature->TNomenclatureDetCombined) && !empty($line->fk_product))
			{
				$tmpline = new stdClass();
				$tmpline->fk_product = $line->fk_product;
				$tmpline->qty = $line->qty;
				$tmpline->calculate_price = $line->total_ht;
				$tmpline->charged_price = $line->total_ht;
				$tmpline->pv = $line->total_ht;
				$tmpline->unit = $TUnits[$line->fk_unit];

				if(! isset($TProduct[$line->fk_product])) $TProduct[$line->fk_product] = $tmpline;
				else {
					$TProduct[$line->fk_product]->qty += $tmpline->qty;
					$TProduct[$line->fk_product]->calculate_price += $tmpline->calculate_price;
					$TProduct[$line->fk_product]->charged_price += $tmpline->charged_price;
					$TProduct[$line->fk_product]->pv += $tmpline->pv;
				}

			}

            foreach($nomenclature->TNomenclatureWorkstationCombined as $fk_ws => $ws) {
                if(isset($TWorkstation[$fk_ws])) {
                    $TWorkstation[$fk_ws]->nb_hour += $ws->nb_hour;
                    $TWorkstation[$fk_ws]->nb_hour_prepare += $ws->nb_hour_prepare;
                    $TWorkstation[$fk_ws]->nb_hour_manufacture += $ws->nb_hour_manufacture;
                }
                else {
                    $TWorkstation[$fk_ws] = $ws;
                }
            }
        }
        else {
            if(TSubtotal::isTitle($line)) {
                $TProduct[$line->id] = array(
                    'products' => array(
                        'T_'.$line->id => $line,
                    ),
                    'total' => array(
                        'unit' => array()
                    )
                );
            }
            else {
                if(TSubtotal::isModSubtotalLine($line)) continue;   // Prevent from subtotal and free text lines

                $TTitle = TSubtotal::getAllTitleFromLine($line);
                $TTitleKeys = array_keys($TTitle);
                $firstParentTitleId = intval($TTitleKeys[0]);

                $nomenclature = new TNomenclature;
                $nomenclature->loadByObjectId($PDOdb, $line->id, $object_type, true, $line->fk_product, $line->qty);

                $nomenclature->fetchCombinedDetails($PDOdb);
                $nomenclature->setPrice($PDOdb, $line->qty, null, 'propal');

                if (! empty($nomenclature->TNomenclatureDetCombined)){ //Produit de line contient une nomenclature
                    $nome = new TNomenclature();
                    $nome->loadByObjectId($PDOdb,$line->fk_product,'product');
                    $nome->fetchCombinedDetails($PDOdb);
                    $nome->setPrice($PDOdb, $line->qty, null, 'propal');
                    foreach ($nomenclature->TNomenclatureDetCombined as $fk_product => $det) {
                        $p = new Product($db);
                        $p->fetch($det->fk_product);
                        $det->type = $p->type;
                        if (empty($det->fk_unit)) $det->fk_unit = 1;
                        $det->unit = $object->getValueFrom('c_units', $det->fk_unit, 'label');

                        // To display warning message if product haven't the same unit as bom
						$det->productCurrentFkUnit = $p->fk_unit;
						$det->productCurrentUnit = $object->getValueFrom('c_units', $p->fk_unit, 'label');
						$det->warningUnitNotTheSameAsProduct = ($det->fk_unit != $p->fk_unit);


                        $det->qty = $det->qty * $line->qty;
                        if (!isset($TProduct[$firstParentTitleId]['products'][$det->fk_product]))
                            $TProduct[$firstParentTitleId]['products'][$det->fk_product] = $det;
                        else {
                                $TProduct[$firstParentTitleId]['products'][$det->fk_product]->qty += $det->qty;
                                $TProduct[$firstParentTitleId]['products'][$det->fk_product]->calculate_price += $det->calculate_price;
                                $TProduct[$firstParentTitleId]['products'][$det->fk_product]->charged_price += $det->charged_price;
                                $TProduct[$firstParentTitleId]['products'][$det->fk_product]->pv += $det->pv;
                        }

                        // Total unit
                        if (!isset($TProduct[$firstParentTitleId]['total']['unit'][$det->unit])) $TProduct[$firstParentTitleId]['total']['unit'][$det->unit] = $det->qty;
                        else $TProduct[$firstParentTitleId]['total']['unit'][$det->unit] += $det->qty;

                        // Total calculate_price
                        if (!isset($TProduct[$firstParentTitleId]['total']['calculate_price'])) $TProduct[$firstParentTitleId]['total']['calculate_price'] = $det->calculate_price;
                        else $TProduct[$firstParentTitleId]['total']['calculate_price'] += $det->calculate_price;

                        // Total charged_price
                        if (!isset($TProduct[$firstParentTitleId]['total']['charged_price'])) $TProduct[$firstParentTitleId]['total']['charged_price'] = $det->charged_price;
                        else $TProduct[$firstParentTitleId]['total']['charged_price'] += $det->charged_price;

                        // Total pv
                        if (!isset($TProduct[$firstParentTitleId]['total']['pv'])) $TProduct[$firstParentTitleId]['total']['pv'] = $det->pv;
                        else $TProduct[$firstParentTitleId]['total']['pv'] += $det->pv;
                    }
                }
				else{ // Produit simple de la ligne
                    if (empty($TUnits) && !$alreadySearched) getUnits();
                    if (!empty($conf->global->NOMENCLATURE_INCLUDE_PRODUCTS_WITHOUT_NOMENCLATURE)) {
                        $tmpline = new stdClass();
                        $tmpline->fk_product = $line->fk_product;
                        $tmpline->qty = $line->qty;
                        $tmpline->calculate_price = $line->total_ht;
                        $tmpline->charged_price = $line->total_ht;
                        $tmpline->pv = $line->total_ht;
                        $tmpline->unit = $TUnits[$line->fk_unit];

						$p = new Product($db);
						$p->fetch($line->fk_product);
						// To display warning message if product haven't the same unit as bom
						$det->productCurrentFkUnit = $p->fk_unit;
						$det->productCurrentUnit = $object->getValueFrom('c_units', $p->fk_unit, 'label');
						$tmpline->warningUnitNotTheSameAsProduct = ($tmpline->fk_unit != $p->fk_unit);

                        if(! isset($TProduct[$firstParentTitleId]['products'][$line->fk_product])) $TProduct[$firstParentTitleId]['products'][$line->fk_product] = $tmpline;
                        else {
                            $TProduct[$firstParentTitleId]['products'][$line->fk_product]->qty += $tmpline->qty;
                            $TProduct[$firstParentTitleId]['products'][$line->fk_product]->calculate_price += $tmpline->calculate_price;
                            $TProduct[$firstParentTitleId]['products'][$line->fk_product]->charged_price += $tmpline->charged_price;
                            $TProduct[$firstParentTitleId]['products'][$line->fk_product]->pv += $tmpline->pv;
                        }

                        // Total unit
                        if(! isset($TProduct[$firstParentTitleId]['total']['unit'][$tmpline->unit])) $TProduct[$firstParentTitleId]['total']['unit'][$tmpline->unit] = $tmpline->qty;
                        else $TProduct[$firstParentTitleId]['total']['unit'][$tmpline->unit] += $tmpline->qty;

                        // Total calculate_price
                        if(! isset($TProduct[$firstParentTitleId]['total']['calculate_price'])) $TProduct[$firstParentTitleId]['total']['calculate_price'] = $tmpline->calculate_price;
                        else $TProduct[$firstParentTitleId]['total']['calculate_price'] += $tmpline->calculate_price;

                        // Total charged_price
                        if(! isset($TProduct[$firstParentTitleId]['total']['charged_price'])) $TProduct[$firstParentTitleId]['total']['charged_price'] = $tmpline->charged_price;
                        else $TProduct[$firstParentTitleId]['total']['charged_price'] += $tmpline->charged_price;

                        // Total pv
                        if(! isset($TProduct[$firstParentTitleId]['total']['pv'])) $TProduct[$firstParentTitleId]['total']['pv'] = $tmpline->pv;
                        else $TProduct[$firstParentTitleId]['total']['pv'] += $tmpline->pv;
                    }
				}

                uasort($TProduct[$firstParentTitleId]['products'], 'sortByProductType');

                foreach($nomenclature->TNomenclatureWorkstationCombined as $fk_ws => $ws) {
                    if(isset($TWorkstation[$fk_ws])) {
                        $TWorkstation[$fk_ws]->nb_hour += $ws->nb_hour;
                        $TWorkstation[$fk_ws]->nb_hour_prepare += $ws->nb_hour_prepare;
                        $TWorkstation[$fk_ws]->nb_hour_manufacture += $ws->nb_hour_manufacture;
                    }
                    else {
                        $TWorkstation[$fk_ws] = $ws;
                    }
                }
            }
        }
    }

    if(! empty($conf->global->NOMENCLATURE_DETAILS_TAB_REWRITE)) {
        // We set the global total
        $TTotal = array('products' => array(), 'total' => array('unit' => array()));
        foreach($TProduct as $TData) {
            foreach($TData['total'] as $unit => $total_unit) {
                if(is_array($total_unit)) {
                    foreach($total_unit as $k => $v) {
                        if(! isset($TTotal['total']['unit'][$k])) $TTotal['total']['unit'][$k] = $v;
                        else $TTotal['total']['unit'][$k] += $v;
                    }
                }
                else {
                    if(! isset($TTotal['total'][$unit])) $TTotal['total'][$unit] = $total_unit;
                    else $TTotal['total'][$unit] += $total_unit;
                }
            }
        }
        $TProduct['gl_total'] = $TTotal;
    }

    return array($TProduct, $TWorkstation);
}

// Product first then Services
function sortByProductType($a) {
    if($a->type == Product::TYPE_PRODUCT) return -1;
    else if($a->type == Product::TYPE_SERVICE) return 1;

    return 0;   // This should never append
}

function print_table($TData, $TWorkstation, $object_type) {
    global $db, $langs, $conf, $id;

    if(empty($conf->global->NOMENCLATURE_DETAILS_TAB_REWRITE)) {
        ?>
        <table class="noorder tagtable liste" width="100%">
            <tr class="liste_titre">
                <th class="liste_titre"><?php echo $langs->trans('Product'); ?></th>
                <th class="liste_titre" align="right"><?php echo $langs->trans('QtyNeed'); ?></th>
                <th class="liste_titre" align="right"><?php echo $langs->trans('RealStock'); ?></th>
                <th class="liste_titre" align="right"><?php echo $langs->trans('TheoreticalStock'); ?></th>
            </tr>
            <?php

            foreach($TData as $fk_product => &$det) {
                $product = new Product($db);
                $product->fetch($fk_product);
                $product->load_stock();

                echo '<tr class="oddeven">
				<td>'.$product->getNomUrl(1).' - '.$product->label.'</td>
				<td align="right">'.price($det->qty).'</td>
				<td align="right">'.price($product->stock_reel).'</td>
				<td align="right">'.price($product->stock_theorique).'</td>
			</tr>
			';
            }

            ?>
            <tr class="liste_titre">
                <th class="liste_titre"><?php echo $langs->trans('Workstation'); ?></th>
                <th class="liste_titre" align="right"><?php echo $langs->trans('QtyNeed'); ?></th>
                <th class="liste_titre" colspan="2"></th>
            </tr>
            <?php
            $total_heure = 0;
            foreach($TWorkstation as &$ws) {
                echo '<tr class="oddeven" >
				<td>'.$ws->workstation->getNomUrl(1).'</td>
				<td align="right">'.price($ws->nb_hour).' h</td>
				<td align="right" colspan="2" ></td>
			</tr>
			';

                $total_heure += $ws->nb_hour;
            }

            ?>
            <tr class="liste_total" style="font-weight:bold">
                <td align="right">Total :</td>
                <td align="right"><?php echo price($total_heure); ?> h</td>
                <td align="right" colspan="2"></td>
            </tr>
        </table>
        <?php
    }
    else {
        $action = GETPOST('action', 'alpha');
        $index_block = GETPOST('index_block', 'int');
        $fk_product_toEdit = GETPOST('fk_product', 'int');

		$showTitleCol = false;
        if ($conf->global->NOMENCLATURE_SHOW_TITLE_IN_COLUMN && empty($conf->global->NOMENCLATURE_HIDE_SUBTOTALS_AND_TITLES))
		{

			foreach ($TData as $k => $tab)
			{
				if (array_key_exists('T_'.$k, $tab['products'])) {
					$lastTitle = '';
					$showTitleCol = true;
					break;
				}
			}
		}
        ?>
        <table class="noorder tagtable liste" width="100%">
            <tr class="liste_titre">
				<?php if ($showTitleCol) print '<th class="liste_titre" >'.$langs->trans('Title').'</th>'; ?>
				<?php if (!empty($conf->global->NOMENCLATURE_SEPARATE_PRODUCT_REF_AND_LABEL)) { ?>
			<th class="liste_titre" ><?php echo $langs->trans('Ref'); ?></th>
			<th class="liste_titre" width="30%" ><?php echo $langs->trans('Product'); ?></th>
				<?php } else { ?>
			<th class="liste_titre" width="40%" ><?php echo $langs->trans('Product'); ?></th>
				<?php } ?>
			<th class="liste_titre" align="right"><?php echo $langs->trans('Quantity'); ?></th>
                <th class="liste_titre" align="right"><?php echo $langs->trans('Unit'); ?></th>
                <?php
                if(! empty($conf->global->NOMENCLATURE_USE_CUSTOM_BUYPRICE)) {
                    print '<th class="liste_titre" align="left">'.$langs->trans('BuyingPriceCustom').'</th>';
                    print '<th class="liste_titre" width="1%" align="left">&nbsp;</th>';
                }
                ?>
                <th class="liste_titre" align="right"><?php echo $langs->trans('AmountCost'); ?></th>
                <th class="liste_titre" align="right"><?php echo $langs->trans('AmountCostWithCharge'); ?></th>
                <th class="liste_titre" align="right"><?php echo $langs->trans('PV'); ?></th>
                <th></th>
            </tr>
            <?php

            foreach($TData as $k => $TBlock) {
            	if ($showTitleCol && $k == "") $lastTitle = '';

            	foreach($TBlock['products'] as $fk_product => $line) {

                	if (!empty($conf->global->NOMENCLATURE_HIDE_SUBTOTALS_AND_TITLES) && strpos($fk_product, 'T_') !== false) continue;

                    $label = $qty = $unit = $calculate_price = $charged_price = $buying_price = $pv = $color = '';

                    if(is_null($line->fk_product)) {    // Title line
                        $product = $line;
                        $niv = TSubtotal::getNiveau($line);

                        if($niv == 1) $color = '#adadcf';
                        else if($niv == 2) $color = '#ddddff';
                        else if($niv > 2) $color = 'rgb(238, 238, 255)';
						if ($showTitleCol)
						{
							$lastTitle = $product->label;
							continue;
						}
                    }
                    else {  // Product / Service Line
                        $product = new Product($db);
                        $product->fetch($line->fk_product);
                        $product->load_stock();

                        $label .= $product->getNomUrl(1).' - ';
                        $qty = price($line->qty);
                        $unit = $langs->trans($line->unit);


                        if($line->warningUnitNotTheSameAsProduct){
                        	$unitTitle = $langs->trans('WarningUnitOfBomIsNotTheSameAsProduct', $langs->trans($line->productCurrentUnit));
                        	$unit = '<span class="badge badge-danger classfortooltip" title="'.$unitTitle.'" >'.$langs->trans($line->unit).'</span>';
						}

                        $calculate_price = price(price2num($line->calculate_price, 'MT'));
                        $charged_price = price(price2num($line->charged_price, 'MT'));
                        $buying_price = price(price2num($line->buying_price, 'MT')); // TODO En mode edit de ligne, le transformer en input type text comme sur les nomenclatures
                        $pv = price(price2num($line->pv, 'MT'));
                    }

                    $label .= $product->label;
                    $style = '';
                    if(! empty($color)) $style = ' style="background: '.$color.'"';

                    print '<tr class="oddeven"'.$style.'>';
					if ($showTitleCol) print '<td>'.$lastTitle.'</td>';
                    if (empty($conf->global->NOMENCLATURE_SEPARATE_PRODUCT_REF_AND_LABEL)) print '<td>'.$label.'</td>';
                    else{
						if(get_class($product) == 'PropaleLigne')
						{
							print '<td>'.$product->label.'</td><td></td>';
						}
						else
						{
							print '<td>'.$product->getNomUrl(1).'</td>';
							print '<td>'.$product->label.'</td>';
						}

					}
                    print '<td align="right">'.$qty.'</td>';
                    print '<td align="right">'.$unit.'</td>';
                    if(! empty($conf->global->NOMENCLATURE_USE_CUSTOM_BUYPRICE)) {
                        if($action == 'edit' && $k == $index_block && $fk_product_toEdit == $line->fk_product) {
                            print '<td nowrap colspan="2">';
                            print '<select id="TNomenclature['.$fk_product_toEdit.'][fk_fournprice]" name="TNomenclature['.$fk_product_toEdit.'][fk_fournprice]" class="flat"></select>';
                            print '<input type="text" name="TNomenclature['.$fk_product.'][buying_price]" value="'.(empty($line->buying_price) ? '' : $line->buying_price).'" size="7" />';
                            print '</td>';

                        }
                        else {
                            print '<td align="left">'.$buying_price.'</td>';

                            print '<td>';
                            if(! is_null($line->fk_product)) {

                                print '<i class="fa fa-edit fa-lg" data-index-block="'.$k.'" data-fk-product="'.$line->fk_product.'"></i>';
                                ?>
                                <script type="text/javascript">
                                    $(document).ready(function() {
                                        $('i.fa-edit[data-index-block=<?php print $k; ?>][data-fk-product=<?php print $line->fk_product; ?>]').on('click', function() {
                                            let form = $('<form action="<?php print $_SERVER['PHP_SELF'].'?id='.$id.'&object='.$object_type; ?>" method="POST">');
                                            form.append('<input type="hidden" name="action" value="save" />');

                                            let select= $('<select>');
                                            select.addClass('flat');
                                            select.attr('id', 'TNomenclature[<?php print $line->fk_product; ?>][fk_fournprice]');
                                            select.attr('name', 'TNomenclature[<?php print $line->fk_product; ?>][fk_fournprice]');

                                            let input = $('<input type="text">');
                                            input.attr('name', 'TNomenclature[<?php print $line->fk_product; ?>][buying_price]');
                                            input.attr('value', '<?php if(! empty($line->buying_price)) { print $line->buying_price; } ?>');
                                            input.attr('size', '7');

                                            let parentToRemove = $(this).parent();
                                            let tdToChange = parentToRemove.prev();

                                            parentToRemove.remove();
                                            tdToChange.text('');
                                            tdToChange.attr('colspan', '2');
                                            tdToChange.attr('nowrap', 'nowrap');

                                            form.append(select);
                                            form.append(input);
                                            form.append($('<input type="submit" class="butAction" name="" value="<?php print $langs->trans('Save'); ?>" />'));
                                            tdToChange.append(form);

                                            // Traitement de la fonction TNomenclature::printSelectProductFournisseurPrice(...)
                                            $.post('<?php echo DOL_URL_ROOT; ?>/fourn/ajax/getSupplierPrices.php?bestpricefirst=1', {'idprod': <?php echo $fk_product; ?> }, function (data) {
                                                    if (data && data.length > 0) {
                                                        var options = '<option value="0" price=""></option>'; // Valeur vide
                                                        var defaultkey = '';
                                                        var defaultprice = '';
                                                        var bestpricefound = 0;

                                                        var bestpriceid = 0;
                                                        var bestpricevalue = 0;
                                                        var pmppriceid = 0;
                                                        var pmppricevalue = 0;
                                                        var costpriceid = 0;
                                                        var costpricevalue = 0;

                                                        /* setup of margin calculation */
                                                        var defaultbuyprice = '<?php

                                                            if(! empty($conf->global->NOMENCLATURE_COST_TYPE)) {
                                                                if($conf->global->NOMENCLATURE_COST_TYPE == '1') print 'bestsupplierprice';
                                                                if($conf->global->NOMENCLATURE_COST_TYPE == 'pmp') print 'pmp';
                                                                if($conf->global->NOMENCLATURE_COST_TYPE == 'costprice') print 'costprice';
                                                            }
                                                            else if(isset($conf->global->MARGIN_TYPE)) {
                                                                if($conf->global->MARGIN_TYPE == '1') print 'bestsupplierprice';
                                                                if($conf->global->MARGIN_TYPE == 'pmp') print 'pmp';
                                                                if($conf->global->MARGIN_TYPE == 'costprice') print 'costprice';
                                                            } ?>';
                                                        console.log('we will set the field for margin. defaultbuyprice=' + defaultbuyprice);

                                                        var i = 0;
                                                        $(data).each(function () {
                                                            if (this.id != 'pmpprice' && this.id != 'costprice') {
                                                                i++;
                                                                this.price = parseFloat(this.price); // to fix when this.price >0
                                                                // If margin is calculated on best supplier price, we set it by defaut (but only if value is not 0)
                                                                if (bestpricefound == 0 && this.price > 0) {
                                                                    defaultkey = this.id;
                                                                    defaultprice = this.price;
                                                                    bestpriceid = this.id;
                                                                    bestpricevalue = this.price;
                                                                    bestpricefound = 1;
                                                                }	// bestpricefound is used to take the first price > 0
                                                            }
                                                            if (this.id == 'pmpprice') {
                                                                // If margin is calculated on PMP, we set it by defaut (but only if value is not 0)
                                                                if ('pmp' == defaultbuyprice || 'costprice' == defaultbuyprice) {
                                                                    if (this.price > 0) {
                                                                        defaultkey = this.id;
                                                                        defaultprice = this.price;
                                                                        pmppriceid = this.id;
                                                                        pmppricevalue = this.price;
                                                                        console.log('pmppricevalue=' + pmppricevalue);
                                                                    }
                                                                }
                                                            }
                                                            if (this.id == 'costprice') {
                                                                // If margin is calculated on Cost price, we set it by defaut (but only if value is not 0)
                                                                if ('costprice' == defaultbuyprice) {
                                                                    if (this.price > 0) {
                                                                        defaultkey = this.id;
                                                                        defaultprice = this.price;
                                                                        costpriceid = this.id;
                                                                        costpricevalue = this.price;
                                                                    } else if (pmppricevalue > 0) {
                                                                        defaultkey = pmppriceid;
                                                                        defaultprice = pmppricevalue;
                                                                    }
                                                                }
                                                            }

                                                            if (this.price == '') {
                                                                this.price = 0;
                                                            }
                                                            options += '<option value="' + this.id + '" price="' + this.price + '">' + this.label + '</option>';
                                                        });

                                                        console.log('finally selected defaultkey=' + defaultkey + ' defaultprice=' + defaultprice);

                                                        var select_fournprice = $('select[name=TNomenclature\\[<?php echo $fk_product; ?>\\]\\[fk_fournprice\\]]');

                                                        select_fournprice.html(options);

                                                        // Préselection de la liste avec la valeur en base si existante
                                                        <?php if(! empty($line->fk_fournprice)) { ?>
                                                        select_fournprice.val('<?php echo $line->fk_fournprice; ?>');
                                                        <?php }else{ ?>
                                                        select_fournprice.val(defaultbuyprice);
                                                        <?php } ?>
                                                        /* At loading, no product are yet selected, so we hide field of buying_price */

                                                        if (select_fournprice.closest('tr').find('input[name*="buying_price"]').val() == '') {
                                                            console.log('init fournprice_predef');
                                                            var pricevalue = select_fournprice.find('option:selected').attr('price');
                                                            select_fournprice.closest('tr').find('input[name*="buying_price"]').attr('placeholder', pricevalue);
                                                            select_fournprice.closest('tr').find('input[name*="buying_price"]').val(pricevalue);
                                                        }

                                                        select_fournprice.change(function () {
                                                            console.log('change on fournprice_predef');
                                                            var linevalue = $(this).find('option:selected').val();
                                                            var pricevalue = $(this).find('option:selected').attr('price');
                                                            $(this).closest('tr').find('input[name*="buying_price"]').val(pricevalue);
                                                            $(this).closest('tr').find('input[name*="buying_price"]').attr('placeholder', '');
                                                        });
                                                    }
                                                },
                                                'json');
                                        });
                                    });
                                </script>
                                <?php
                            }
                            print '</td>';
                        }
                    }

                    print '<td align="right">'.$calculate_price.'</td>';
                    print '<td align="right">'.$charged_price.'</td>';
                    print '<td align="right">'.$pv.'</td>';
                    print '<td></td>';
                    print '</tr>';
                }

                if (empty($conf->global->NOMENCLATURE_HIDE_SUBTOTALS) && empty($conf->global->NOMENCLATURE_HIDE_SUBTOTALS_AND_TITLES))
				{
					if($k == 'gl_total') print '<tr style="font-weight: bold;">';
					else print '<tr class="liste_total">';

					if (!empty($conf->global->NOMENCLATURE_SEPARATE_PRODUCT_REF_AND_LABEL)) {
						if ($k == 'gl_total')
						{
							print '<td align="left">'.$langs->trans('Total').' :</td><td></td>';
						}
						else
						{
							print '<td></td><td align="right">'.$langs->trans('Total').' :</td>';
						}
					} else print '<td align="'.(($k == 'gl_total') ? 'left' : 'right').'" >'.$langs->trans('Total').' :</td>';

					print '<td align="right">';
					foreach($TBlock['total']['unit'] as $unit => $total_unit) {
						print "<div>".price(price2num($total_unit, 'MT'))."</div>\n";
					}
					print '</td>';

					print '<td align="right">';
					foreach($TBlock['total']['unit'] as $unit => $total_unit) {
						print "<div>".$langs->trans($unit)."</div>\n";
					}
					print '</td>';

					if(! empty($conf->global->NOMENCLATURE_USE_CUSTOM_BUYPRICE)) {
						print '<td align="right" colspan="2"></td>';
					}

					print '<td align="right">'.price(price2num($TBlock['total']['calculate_price'], 'MT')).'</td>';
					print '<td align="right">'.price(price2num($TBlock['total']['charged_price'], 'MT')).'</td>';
					print '<td align="right">'.price(price2num($TBlock['total']['pv'], 'MT')).'</td>';
					print '<td></td>';
					print '</tr>';
				}

            }

            if(! empty($TWorkstation)) {
                ?>
                <tr class="liste_titre">
                    <th class="liste_titre"><?php echo $langs->trans('WorkStation'); ?></th>
                    <th class="liste_titre" align="right"><?php echo $langs->trans('Qty'); ?></th>
                    <th class="liste_titre" colspan="3"></th>
                </tr>
                <?php

                $total_heure = 0;
                foreach($TWorkstation as &$ws) {
                    echo '<tr class="oddeven">
                    <td>'.$ws->workstation->getNomUrl(1).'</td>
                    <td align="right">'.price($ws->nb_hour).' h</td>
                    <td align="right" colspan="3"></td>
                </tr>
                ';

                    $total_heure += $ws->nb_hour;
                }

                ?>
                <tr class="liste_total" style="font-weight: bold;">
                    <td align="right">Total :</td>
                    <td align="right"><?php echo price($total_heure); ?> h</td>
                    <td align="right" colspan="3"></td>
                </tr>
                <?php
            }
            ?>
        </table>
        <br/>
        <?php
    }
}
