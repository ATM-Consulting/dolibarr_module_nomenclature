<?php

	require 'config.php';
	dol_include_once('/nomenclature/class/nomenclature.class.php');
	
	$langs->load('nomenclature@nomenclature');
	
	$object_type = GETPOST('object');	
	$id = (int)GETPOST('id');
	
	if($object_type =='propal') {
		dol_include_once('/comm/propal/class/propal.class.php');
		$object = new Propal($db);
		$object->fetch($id);

	}
	
	if(empty($object))exit;
	$PDOdb=new TPDOdb;
	_drawlines($object, $object_type);
	
	
	
function _drawlines(&$object, $object_type) {
	global $db,$langs,$conf,$PDOdb;
	
	llxHeader('', 'Nomenclatures', '', '', 0, 0, array('/nomenclature/js/speed.js','/nomenclature/js/jquery-sortable-lists.min.js'), array('/nomenclature/css/speed.css'));
	
	if($object_type == 'propal') {
		dol_include_once('/core/lib/propal.lib.php');
		$head = propal_prepare_head($object);
		dol_fiche_head($head, 'nomenclature', $langs->trans('Proposal'), 0, 'propal');
		
	}
	
	$formDoli=new Form($db);
	$formCore=new TFormCore;
	echo '<div id="addto" style="float:right; width:200px;">';
		$formDoli->select_produits(-1,'fk_product');
		echo $formCore->bt($langs->trans('AddProductNomenclature'), 'AddProductNomenclature');
		
		echo $formCore->combo('', 'fk_new_workstation',TWorkstation::getWorstations($PDOdb, false, true), -1);
		echo $formCore->bt($langs->trans('AddWorkstation'), 'AddWorkstation');
		
		echo '<hr />';
		echo $formCore->bt($langs->trans('SaveAll'), 'SaveAll');
		
	echo '</div>';
	
	echo '<ul id="speednomenclature" class="lines '.$object->element.'">';
	
	foreach($object->lines as $k=>&$line) {
		
		if($line->fk_product_type == 0 && $line->fk_product>0) {
			
			$product = new Product($db);
			$product->fetch($line->fk_product);
			
			echo '<li k="'.$k.'" class="product" object_type="'.$object->element.'" id="line-'.$line->id.'"  fk_object="'.$line->id.'" fk_product="'.$line->fk_product.'"><div>'.$product->getNomUrl(1).' '.$product->label;
			
			echo '</div>';
			
			_drawnomenclature($line->id, $object->element,$line->fk_product,$line->qty);
			
			echo '</li>';	
		}
		
			
	}

	echo '</ul>';
	?>
	<div class="logme"></div>
	<?php
	dol_fiche_end();
	llxFooter();
} 
function _drawnomenclature($fk_object, $object_type,$fk_product,$qty) {
	global $db,$langs,$conf,$PDOdb;
	
	
	$nomenclature=new TNomenclature;
	$nomenclature->loadByObjectId($PDOdb, $fk_object, $object_type, true,$fk_product,$qty);
	
	if(!empty($nomenclature->TNomenclatureDet) || !empty($nomenclature->TNomenclatureWorkstation)) {
		
		if($nomenclature->iExist) {
			echo '<ul class="lines nomenclature" fk_nomenclature="'.$nomenclature->getId().'">';
		}
		else {
			echo '<ul class="lines notanomenclature" fk_nomenclature="0" fk_original_nomenclature="'.$nomenclature->fk_nomenclature_parent.'">';
			echo '<div>'.$langs->trans('PseudoNomenclature') .img_help('',$langs->trans('PseudoNomenclatureInfo')  ).'</div>';
		}
				
		
		foreach($nomenclature->TNomenclatureDet as $k=>&$line) {
			$product = new Product($db);
			$product->fetch($line->fk_product);
			$id = $line->getId() > 0 ? $line->getId() : $nomenclature->fk_nomenclature_parent.'-'.$k;
			
			
			echo '<li class="nomenclature" k="'.$k.'" line-type="nomenclature" id="nomenclature-product-'.$id.'" object_type="product" fk_object="'.$line->fk_product.'"><div>'.$product->getNomUrl(1).' '.$product->label.'</div>';
				_drawnomenclature($product->id, 'product',$product->id,$line->qty * $qty);		
			echo '</li>';
		}
		
		foreach($nomenclature->TNomenclatureWorkstation as $k=>&$ws) {
			echo '<li class="nomenclature workstation"  k="'.$k.'" object_type="workstation" id="nomenclature-ws-'.$ws->getId().'" fk_object="'.$ws->workstation->getId().'"><div>'.$ws->workstation->name.'</div>';
			echo '</li>';
		}
		
		echo '</ul>';
	}
	
}
