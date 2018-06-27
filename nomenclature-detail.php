<?php

	require 'config.php';
	dol_include_once('/nomenclature/class/nomenclature.class.php');
	
	$langs->load('nomenclature@nomenclature');
	
	$object_type = GETPOST('object');	
	$id = (int)GETPOST('id');

	if(GETPOST('save')=='ok') setEventMessage($langs->trans('Saved'));
	
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
	
	_drawlines($object, $object_type);
	
	
function _drawHeader($object, $object_type) {
global $db,$langs,$conf,$PDOdb;
	
	if($object_type == 'propal') {
		dol_include_once('/core/lib/propal.lib.php');
		$head = propal_prepare_head($object);
		dol_fiche_head($head, 'nomenclature', $langs->trans('Proposal'), -1, 'propal');

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
                dol_fiche_head($head, 'nomenclature', $langs->trans('CustomerOrder'), -1, 'order');

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

	
}
	
function _getDetails(&$object, $object_type) {
	
	$PDOdb = new TPDOdb;
	
	global $db,$langs,$conf,$PDOdb,$TProductAlreadyInPage;
	
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
		
		foreach($nomenclature->TNomenclatureWorkstationCombined as $fk_ws=> $ws) {
			if(isset($TWorkstation[$fk_ws])) {
				$TWorkstation[$fk_ws]->nb_hour+=$ws->nb_hour;
				$TWorkstation[$fk_ws]->nb_hour_prepare+=$ws->nb_hour_prepare;
				$TWorkstation[$fk_ws]->nb_hour_manufacture+=$ws->nb_hour_manufacture;
			}
			else{
				$TWorkstation[$fk_ws] = $ws;
			}
		} 
		
	}
	
	return array($TProduct,$TWorkstation); 
	
	
}
	
function _drawlines(&$object, $object_type) {
	global $db,$langs,$conf,$PDOdb,$TProductAlreadyInPage;
	
	llxHeader('', 'Nomenclatures', '', '', 0, 0, array('/nomenclature/js/speed.js','/nomenclature/js/jquery-sortable-lists.min.js'), array('/nomenclature/css/speed.css'));
	
	_drawHeader($object, $object_type);
	
	list($TProduct,$TWorkstation) = _getDetails($object, $object_type);
	
	$langs->load('workstation@workstation');
	
	$formDoli=new Form($db);
	$formCore=new TFormCore;
	
	?>
	<table class="border" width="100%">
		<tr class="liste_titre">
			<td class="liste_titre"><?php echo $langs->trans('Product') ?></td>
			<td class="liste_titre"><?php echo $langs->trans('Qty') ?></td>
		</tr>
	<?php
		
		dol_include_once('/product/class/product.class.php');
		
		foreach($TProduct as $fk_product=> &$det) {
			
			$product=new Product($db);
			$product->fetch($fk_product);
			
			echo '<tr>
				<td>'.$product->getNomUrl(1).' - '.$product->label.'</td>
				<td align="right">'.price($det->qty).'</td>
			</tr>
			';
			
		}
	
	?>
	<tr class="liste_titre">
		<td class="liste_titre"><?php echo $langs->trans('WorkStation') ?></td>
		<td class="liste_titre"><?php echo $langs->trans('Qty') ?></td>
	</tr>
	<?php
		$total_heure = 0;
		foreach($TWorkstation as &$ws) {
			
			echo '<tr>
				<td>'.$ws->workstation->getNomUrl(1).'</td>
				<td align="right">'.price($ws->nb_hour).' h</td>
			</tr>
			';
			
			$total_heure+=$ws->nb_hour;
		}
		
	?>
	<tr  style="font-weight:bold">
		<td align="right">Total : </td><td align="right"><?php echo price($total_heure) ?> h</td>
	</tr>
	</table>
	<?php
	
	dol_fiche_end();
	llxFooter();
} 
