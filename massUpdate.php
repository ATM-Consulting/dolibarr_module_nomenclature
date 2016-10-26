<?php

	require 'config.php';
	
	if(empty($user->rights->nomenclature->global->massUpdate))accessforbidden();
	
	dol_include_once('/product/class/product.class.php');
	dol_include_once('/nomenclature/class/nomenclature.class.php');
	
	$action = GETPOST('action');
	$make_it = GETPOST('make_it');
	$fk_product = (int)GETPOST('fk_product');
	$coef = (double)GETPOST('coef');
	
	$PDOdb = new TPDOdb;
	
	llxHeader();
	dol_fiche_head();
	print_fiche_titre($langs->trans('massUpdate'));
	
	$formCore = new TFormCore('auto','formN','post');
	echo $formCore->hidden('action', 'update');
	
	$form->select_produits($fk_product, 'fk_product');
	
	echo $formCore->texte($langs->trans('PercentUP'), 'coef', $coef, 3).'%';
	
	echo '&nbsp;&nbsp;&nbsp;' . $formCore->btsubmit($langs->trans('ShowImpact'), 'bt_show');
	
	flush();
	
	if($action == 'update' && $fk_product>0) {
		
		$TCoef = TNomenclatureCoef::loadCoef($PDOdb);
		
		$Tab = $PDOdb->ExecuteAsArray("SELECT nd.rowid, n.fk_object, qty, product_type,code_type 
						FROM ".MAIN_DB_PREFIX."nomenclaturedet nd
							LEFT JOIN ".MAIN_DB_PREFIX."nomenclature n ON (nd.fk_nomenclature = n.rowid)
						WHERE nd.fk_product = ".$fk_product." AND n.object_type='product'");
	
		if(!empty($Tab)) {
			
			echo '<hr /><table class="border" width="100%">
				<tr class="titre">
					<td>'.$langs->trans('Product').'</td><td>'.$langs->trans('Type').'</td><td>'.$langs->trans('Qty').'</td><td>'.$langs->trans('QtyAfter').'</td></tr>';
			
			foreach($Tab as &$row) {
				
				$p=new Product($db);
				
				if($row->fk_object>0 && $p->fetch($row->fk_object)>0) {
					$bc = (empty($bc) || $bc == 'pair') ? 'impair' : 'pair';
					
					echo '<tr class="'.$bc.'">
						<td>'.$p->getNomUrl(1).'</td>
						<td>'.$TCoef[$row->code_type]->label.'</td>
						<td>'.price($row->qty).'</td>
						<td>'.price($row->qty * ( (100 +  $coef) / 100)  ).'</td>
					</tr>
					';
					
					
				}
				
				
				
				
			}
			
			
			echo '</table>';
			
			if(empty($make_it)) {
				
				echo '<div class="tabsAction">'.$formCore->btsubmit($langs->trans('MakeIt'), 'make_it').'</div>';
				
			}
			else{
	
					$res = $PDOdb->Execute(" UPDATE ".MAIN_DB_PREFIX."nomenclaturedet 
								SET qty = qty * ( (100 +  ".$coef.") / 100) 
								WHERE fk_product = ".$fk_product );
								
					echo '<div class="info">Mise à jour effectuée</div>';
					
			}
			
		}
	
		
	}
	
	$formCore->end();
	
	dol_fiche_end();
	llxFooter();
