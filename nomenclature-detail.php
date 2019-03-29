<?php

require 'config.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/propal.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/order.lib.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
dol_include_once('/nomenclature/class/nomenclature.class.php');
if(! class_exists('TSubtotal')) dol_include_once('/subtotal/class/subtotal.class.php');

$langs->load('nomenclature@nomenclature');
$langs->load('workstation@workstation');

$object_type = GETPOST('object');
$id = GETPOST('id', 'int');
$ref = GETPOST('ref');

if(GETPOST('save') == 'ok') setEventMessage($langs->trans('Saved'));

$form = new Form($db);
$PDOdb = new TPDOdb;

/*
 * Action
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
        $morehtmlref .= '<br>'.$langs->trans('Project').' :';

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
    $morehtmlref .= '<br>'.$langs->trans('ThirdParty').' : '.$soc->getNomUrl(1);

    if(empty($conf->global->MAIN_DISABLE_OTHER_LINK) && $object->thirdparty->id > 0) {
        $morehtmlref .= ' (<a href="'.DOL_URL_ROOT.'/commande/list.php?socid='.$object->thirdparty->id.'&search_societe='.urlencode($object->thirdparty->name).'">'.$langs->trans("OtherOrders").'</a>)';
    }

    // Project
    if(! empty($conf->projet->enabled)) {
        $langs->load("projects");
        $morehtmlref .= '<br>'.$langs->trans('Project').' :';

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

/*
 * View
 */
$TProductAlreadyInPage = array();
llxHeader('', 'Nomenclatures', '', '', 0, 0, array('/nomenclature/js/speed.js', '/nomenclature/js/jquery-sortable-lists.min.js'), array('/nomenclature/css/speed.css'));

dol_fiche_head($head, 'nomenclature', $title, -1, $picto);

dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref, '&object='.$object_type);

list($TProduct, $TWorkstation) = _getDetails($object, $object_type);
print_table($object, $TProduct, $TWorkstation);

dol_fiche_end();
llxFooter();

function _getDetails(&$object, $object_type) {
    global $db, $PDOdb, $conf;
    dol_include_once('/subtotal/class/subtotal.class.php');

    $TProduct = array();
    $TWorkstation = array();

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
                        'prices' => array()
                    )
                );
            }
            else {
                if(TSubtotal::isModSubtotalLine($line)) continue;   // Prevent from subtotal and free text lines

                $TTitle = TSubtotal::getAllTitleFromLine($line);
                $TTitleKeys = array_keys($TTitle);
                $firstParentTitleId = $TTitleKeys[0];

                $nomenclature = new TNomenclature;
                $nomenclature->loadByObjectId($PDOdb, $line->id, $object_type, true, $line->fk_product, $line->qty);

                $nomenclature->fetchCombinedDetails($PDOdb);
                $nomenclature->setPrice($PDOdb, $line->qty, null, 'propal');

                foreach($nomenclature->TNomenclatureDetCombined as $fk_product => $det) {
                    unset($det->TChamps, $det->TConstraint);

                    $p = new Product($db);
                    $p->fetch($det->fk_product);
                    $p->load_stock();

                    $det->type = $p->type;
                    $det->unit = $object->getValueFrom('c_units', $det->fk_unit, 'label');
                    $TProduct[$firstParentTitleId]['products'][$det->fk_product] = $det;

                    // Total unit
                    if(! isset($TProduct[$firstParentTitleId]['total'][$det->unit])) $TProduct[$firstParentTitleId]['total'][$det->unit] = $det->qty;
                    else $TProduct[$firstParentTitleId]['total'][$det->unit] += $det->qty;

                    // Total calculate_price
                    if(! isset($TProduct[$firstParentTitleId]['total']['prices']['calculate_price'])) $TProduct[$firstParentTitleId]['total']['prices']['calculate_price'] = $det->calculate_price;
                    else $TProduct[$firstParentTitleId]['total']['prices']['calculate_price'] += $det->calculate_price;

                    // Total charged_price
                    if(! isset($TProduct[$firstParentTitleId]['total']['prices']['charged_price'])) $TProduct[$firstParentTitleId]['total']['prices']['charged_price'] = $det->charged_price;
                    else $TProduct[$firstParentTitleId]['total']['prices']['charged_price'] += $det->charged_price;

                    // Total pv
                    if(! isset($TProduct[$firstParentTitleId]['total']['prices']['pv'])) $TProduct[$firstParentTitleId]['total']['prices']['pv'] = $det->pv;
                    else $TProduct[$firstParentTitleId]['total']['prices']['pv'] += $det->pv;
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
        $TTotal = array('products' => array(), 'total' => array('prices' => array()));
        foreach($TProduct as $TData) {
            foreach($TData['total'] as $unit => $total_unit) {
                if(is_array($total_unit)) {
                    foreach($total_unit as $k => $v) {
                        if(! isset($TTotal['total']['prices'][$k])) $TTotal['total']['prices'][$k] = $v;
                        else $TTotal['total']['prices'][$k] += $v;
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

function print_table($object, $TData, $TWorkstation) {
    global $db, $langs, $conf;

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
                <th class="liste_titre"><?php echo $langs->trans('WorkStation'); ?></th>
                <th class="liste_titre" align="right"><?php echo $langs->trans('Qty'); ?></th>
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

        ?>
        <table class="noorder tagtable liste" width="100%">
            <tr class="liste_titre">
                <th class="liste_titre" width="50%"><?php echo $langs->trans('Product'); ?></th>
                <th class="liste_titre" align="right"><?php echo $langs->trans('QtyNeed'); ?></th>
                <th class="liste_titre" align="right"><?php echo $langs->trans('Unit'); ?></th>
                <th class="liste_titre" align="left"><?php echo $langs->trans('BuyingPriceCustom'); ?></th>
                <th class="liste_titre" align="right"><?php echo $langs->trans('AmountCost'); ?></th>
                <th class="liste_titre" align="right"><?php echo $langs->trans('AmountCostWithCharge'); ?></th>
                <th class="liste_titre" align="right"><?php echo $langs->trans('PV'); ?></th>
                <th class="liste_titre" align="right">&nbsp;</th>
            </tr>
            <?php

            foreach($TData as $k => $TBlock) {
                foreach($TBlock['products'] as $line) {
                    $label = $qty = $unit = $calculate_price = $charged_price = $pv = $color = '';

                    if(is_null($line->fk_product)) {    // Title line
                        $product = $line;
                        $niv = TSubtotal::getNiveau($line);

                        if($niv == 1) $color = '#adadcf';
                        else if($niv == 2) $color = '#ddddff';
                        else if($niv > 2) $color = 'rgb(238, 238, 255)';
                    }
                    else {  // Product / Service Line
                        $product = new Product($db);
                        $product->fetch($line->fk_product);
                        $product->load_stock();

                        $label .= $product->getNomUrl(1).' - ';
                        $qty = $line->qty;
                        $unit = $langs->trans($line->unit);
                        $calculate_price = price($line->calculate_price);
                        $charged_price = price($line->charged_price);
                        $pv = price($line->pv);
                    }

                    $label .= $product->label;
                    $style = '';
                    if(! empty($color)) $style = ' style="background: '.$color.'"';

                    print '<tr class="oddeven"'.$style.'>';
                    print '<td>'.$label.'</td>';
                    print '<td align="right">'.$qty.'</td>';
                    print '<td align="right">'.$unit.'</td>';

                    if(! empty($conf->global->NOMENCLATURE_USE_CUSTOM_BUYPRICE) && ! is_null($line->fk_product)) {
                        print '<td nowrap><select id="TNomenclature['.$k.'][fk_fournprice]" name="TNomenclature['.$k.'][fk_fournprice]" class="flat"></select>';
                        print '<input type="text" name="TNomenclature['.$k.'][buying_price]" value="'.(empty($line->buying_price) ? '' : $line->buying_price).'" size="7" />';
                        print '</td>';
                        $line->printSelectProductFournisseurPrice($k, $line->fk_nomenclature, ($line->type == Product::TYPE_PRODUCT) ? 'product' : 'service');
                    }
                    else print '<td></td>';

                    print '<td align="right">'.$calculate_price.'</td>';
                    print '<td align="right">'.$charged_price.'</td>';
                    print '<td align="right">'.$pv.'</td>';
                    print '<td align="right"></td>';
                    print '</tr>';
                }

                foreach($TBlock['total'] as $unit => $total_unit) {
                    $calculate_price = $charged_price = $pv = $label_unit = $total = '';

                    if(is_array($total_unit)) {
                        $calculate_price = $total_unit['calculate_price'];
                        $charged_price = $total_unit['charged_price'];
                        $pv = $total_unit['pv'];
                    }
                    else {
                        $label_unit = $langs->trans($unit);
                        $total = price($total_unit);
                    }

                    if($k == 'gl_total') print '<tr style="font-weight: bold;">';
                    else print '<tr class="liste_total">';

                    print '<td align="'.(($k == 'gl_total') ? 'left' : 'right').'">'.$langs->trans('Total').' :</td>';
                    print '<td align="right">'.$total.'</td>';
                    print '<td align="right">'.$label_unit.'</td>';
                    print '<td align="right"></td>';
                    print '<td align="right">'.price($calculate_price).'</td>';
                    print '<td align="right">'.price($charged_price).'</td>';
                    print '<td align="right">'.price($pv).'</td>';
                    print '<td align="right"></td>';
                    print '</tr>';
                }
            }

            if(! empty($TWorkstation)) {
                ?>
                <tr class="liste_titre">
                    <th class="liste_titre"><?php echo $langs->trans('WorkStation'); ?></th>
                    <th class="liste_titre" align="right"><?php echo $langs->trans('Qty'); ?></th>
                    <th class="liste_titre" colspan="2"></th>
                </tr>
                <?php

                $total_heure = 0;
                foreach($TWorkstation as &$ws) {
                    echo '<tr class="oddeven">
                    <td>'.$ws->workstation->getNomUrl(1).'</td>
                    <td align="right">'.price($ws->nb_hour).' h</td>
                    <td align="right" colspan="2"></td>
                </tr>
                ';

                    $total_heure += $ws->nb_hour;
                }

                ?>
                <tr class="liste_total" style="font-weight: bold;">
                    <td align="right">Total :</td>
                    <td align="right"><?php echo price($total_heure); ?> h</td>
                    <td align="right" colspan="2"></td>
                </tr>
                <?php
            }
            ?>
        </table>
        <br/>
        <?php
    }
}
