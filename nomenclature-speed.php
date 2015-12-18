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
	_drawlines($object);
	
	
	
function _drawlines(&$object) {
	global $db,$langs,$conf,$PDOdb;
	
	llxHeader('', 'Nomenclatures', '', '', 0, 0, array('/nomenclature/js/speed.js','/nomenclature/js/jquery-sortable-lists.min.js'), array('/nomenclature/css/speed.css'));
	
	$formDoli=new Form($db);
	$formCore=new TFormCore;
	echo '<div id="addto" style="float:right; width:200px;">';
		$formDoli->select_produits(-1,'fk_product');
		echo $formCore->bt($langs->trans('AddProductNomenclature'), 'AddProductNomenclature');
		
		echo $formCore->combo('', 'fk_new_workstation',TWorkstation::getWorstations($PDOdb, false, true), -1);
		echo $formCore->bt($langs->trans('AddWorkstation'), 'AddWorkstation');
	echo '</div>';
	
	echo '<ul id="speednomenclature" class="lines '.$object->element.'">';
	
	foreach($object->lines as &$line) {
		
		if($line->fk_product_type == 0 && $line->fk_product>0) {
			
			$product = new Product($db);
			$product->fetch($line->fk_product);
			
			echo '<li class="product" line-type="product" id="'.$line->fk_product.'"><div>'.$product->getNomUrl(1).' '.$product->label;
			
			echo '</div>';
			
			_drawnomenclature($line->id, 'propal',$line->fk_product,$line->qty);
			
			echo '</li>';	
		}
		
			
	}

	echo '</ul>';
	?>
	<div class="logme"></div>
	<?php
	llxFooter();
} 
function _drawnomenclature($fk_object, $object_type,$fk_product,$qty) {
	global $db,$langs,$conf,$PDOdb;
	
	
	$nomenclature=new TNomenclature;
	$nomenclature->loadByObjectId($PDOdb, $fk_object, $object_type, true,$fk_product,$qty);
	
	if(!empty($nomenclature->TNomenclatureDet) || !empty($nomenclature->TNomenclatureWorkstation)) {
		echo '<ul class="lines nomenclature" id="'.$nomenclature->getId().'">';
		
		foreach($nomenclature->TNomenclatureDet as &$line) {
			$product = new Product($db);
			$product->fetch($line->fk_product);
			echo '<li class="nomenclature" line-type="nomenclature" id="'.$line->getId().'"><div>'.$product->getNomUrl(1).' '.$product->label.'</div>';
				_drawnomenclature($product->id, 'product',$product->id,$line->qty * $qty);		
			echo '</li>';
		}
		
		foreach($nomenclature->TNomenclatureWorkstation as &$ws) {
			echo '<li class="nomenclature workstation" line-type="workstation" id="'.$ws->workstation->getId().'"><div>'.$ws->workstation->name.'</div>';
			echo '</li>';
		}
		
		echo '</ul>';
	}
	
}
