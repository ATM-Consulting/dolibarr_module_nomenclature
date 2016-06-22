<?php

	require '../config.php';

	dol_include_once('/core/lib/admin.lib.php');
	dol_include_once('/nomenclature/lib/nomenclature.lib.php');
	dol_include_once('/nomenclature/class/nomenclature.class.php');
	
	dol_include_once('/product/class/product.class.php');
	
	$langs->load("nomenclature@nomenclature");
	$PDOdb = new TPDOdb;
			
	// Access control
	if (! $user->admin) {
	    accessforbidden();
	}
	
	_card($PDOdb);
	
function _card(&$PDOdb) {
	global $langs, $user;
	
	llxHeader();
	
	$head = nomenclatureAdminPrepareHead();
	dol_fiche_head(
	    $head,
	    'import',
	    $langs->trans("Module104580Name"),
	    0,
	    "nomenclature@nomenclature"
	);	
	
	$formCore=new TFormCore('auto','formImport','post',true);
	
	echo $formCore->fichier('Fichier source'.img_help(1, 'Numéro nomenclature,Id produit, Id Composant, Qté, Qté de référence, Code type') , 'file1', '', 40);
	echo $formCore->btsubmit('Voir', 'bt_view');
	
	_import_to_session();
	
	_show_tab_session($PDOdb);
	
	$formCore->end();
	
	
	dol_fiche_end();
	
	llxFooter();
}

function _show_tab_session(&$PDOdb) {
	global $langs,$db, $user;
	
	$Tab = &$_SESSION['TDataImport'];
	
	$save = GETPOST('bt_save') ? true : false;
	//var_dump($Tab);
	foreach($Tab as $fk_product=>$TNomenclature) {
		
		$p=new Product($db);	
		if($p->fetch($fk_product)<=0) continue;
		
		echo '<hr />'.$p->getNomUrl(1).' - '.$p->label;
			
		foreach($TNomenclature as $TData) {
			
			$n=new TNomenclature;
			$n->fk_object = $fk_product;
			$n->type_object = 'product';
			
			foreach($TData as $data) {
				if(!empty($data['qty_ref']))$n->qty_reference = (double)$data['qty_ref'];
				
				$p_compo=new Product($db);	
				if($p_compo->fetch($data['fk_product_composant'])<=0) continue;
				
				$k = $n->addChild($PDOdb, 'TNomenclatureDet');
				$n->TNomenclatureDet[$k]->fk_product = $data['fk_product_composant'];
				$n->TNomenclatureDet[$k]->qty = $data['qty'];
				$n->TNomenclatureDet[$k]->code_type = $data['type'];
				$n->TNomenclatureDet[$k]->product = $p_compo;
			}
			
			if($save) $n->save($PDOdb);
			
			_show_nomenclature($n);
			
		}
		
	}
	
	if(!$save) {
		$formCore=new TFormCore;
		echo '<div class="tabsAction">';
		echo $formCore->btsubmit('Sauvegarder', 'bt_save');
		echo '</div>';
	}
	else {
		print 'Nomenclatures créées';
		
	}
}

function _show_nomenclature(&$n) {
	
	global $langs,$db, $user;
	
	echo '<br />Pour : '.$n->qty_reference;
	
	if($n->getId()>0) echo '<br />Id nomenclature créée : '.$n->getId();
	
	echo '<table class="border" width="100%"><tr class="liste_titre"><td>Type</td><td>Composant</td><td>Qté</td></tr>';
	
	foreach($n->TNomenclatureDet as &$det) {
		
		echo '<tr>
			<td width="10%">'.$det->code_type.'</td>
			<td width="70%">'.$det->product->getNomUrl(1).' - '.$det->product->label.'</td>
			<td  width="20%" align="right">'.price($det->qty).'</td>
		</tr>';
		
	}
	
	
	echo '</table>';
	
}

function _import_to_session() {
	
	if(GETPOST('bt_view') && !empty($_FILES['file1']['name'])) {
		$Tab = &$_SESSION['TDataImport'];
		$Tab = array();

		$f1 = fopen($_FILES['file1']['tmp_name'],'r');
		
		if($f1 === false) exit('Houston ? ');
		
		while(!feof($f1)) {
			
			$row = fgetcsv($f1, 4096, ',', '"');
			
			$num_nomenclature = (int)$row[0];
			if(empty($num_nomenclature)) $num_nomenclature = 1;
			
			$fk_product = (int)$row[1];
			if(empty($fk_product)) continue;
			
			$fk_product_composant = $row[2]; // produit ou code WS
			if(empty($fk_product_composant)) continue;
			
			$qty = (double)$row[3];
			$qty_ref = (double)$row[4];
			$type = $row[5];
			
			if(empty($Tab[$fk_product]))$Tab[$fk_product]=array();
			if(empty($Tab[$fk_product][$num_nomenclature]))$Tab[$fk_product][$num_nomenclature]=array();
			
			$Tab[$fk_product][$num_nomenclature][]=array(
				'fk_product_composant'=>$fk_product_composant
				,'qty'=>$qty
				,'qty_ref'=>$qty_ref
				,'type'=>$type
			);
			
		}
		
		
	}
	
}
