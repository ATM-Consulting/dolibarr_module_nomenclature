<?php

	require 'config.php';
	dol_include_once('/nomenclature/class/nomenclature.class.php');
	
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
	global $db,$langs,$conf;
	
	llxHeader('', 'Nomenclatures', '', '', 0, 0, array('/nomenclature/js/speed.js','/nomenclature/js/jquery-sortable-lists.min.js'), array('/nomenclature/css/speed.css'));
	
	echo '<ul id="speednomenclature" class="lines '.$object->element.'">';
	
	foreach($object->lines as &$line) {
		
		if($line->fk_product_type == 0 && $line->fk_product>0) {
			
			$product = new Product($db);
			$product->fetch($line->fk_product);
			
			echo '<li class="product" line-type="product" id="'.$line->fk_product.'"><div>'.$product->getNomUrl(1).' '.$product->label.'</div>';
			
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
	
	if(!empty($nomenclature->TNomenclatureDet)) {
		echo '<ul class="lines nomenclature" id="'.$nomenclature->getId().'">';
		
		foreach($nomenclature->TNomenclatureDet as $line) {
			$product = new Product($db);
			$product->fetch($line->fk_product);
			echo '<li class="nomenclature" line-type="nomenclature" id="'.$line->getId().'"><div>'.$product->getNomUrl(1).' '.$product->label.'</div></li>';
				
			
		}
		
		echo '</ul>';
	}
	
}
