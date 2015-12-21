<?php

    require('../config.php');
    dol_include_once('/nomenclature/class/nomenclature.class.php');
    if($conf->workstation->enabled) {
        dol_include_once('/workstation/class/workstation.class.php');
    }

    $PDOdb = new TPDOdb;

    $get = GETPOST('get');
    $put = GETPOST('put');
    
    _get($PDOdb,$get);
    _put($PDOdb,$put);
    
function _get(&$PDOdb, $case) {
    
    switch ($case) {
        case 'nomenclature-line':
            
            __out(_get_nomenclature_line());
            
            break;
        
    }
    
}
function _put(&$PDOdb, $case) {
    
    switch ($case) {
        case 'nomenclature-line':
            
            break;
		case 'nomenclatures':
			
			_putHierarchie($PDOdb,GETPOST('THierarchie'));
			
			break;
    }
    
}
function _get_nomenclature_line() {
	
	
	
}
function _putHierarchie(&$PDOdb, $THierarchie,$fk_object=0,$object_type='') {
	
	pre($THierarchie,true);
	
	if($object_type!='') {
		$nomenclature=new TNomenclature;
		$nomenclature->loadByObjectId($PDOdb, $fk_object, $object_type);
	}
	
	foreach($nomenclature->TNomenclatureDet as &$det) {
		$det->to_delete = true;
	}
	foreach($nomenclature->TNomenclatureWorkstation as &$det) {
		$det->to_delete = true;
	}
	
	foreach($THierarchie as &$line) {
		
		$k = $line['k'];	
		if($line['object_type'] == 'product') {
			if(empty($nomenclature->TNomenclatureDet[$k])) {
				$nomenclature->TNomenclatureDet[$k]=new TNomenclatureDet;
			}
			$nomenclature->TNomenclatureDet[$k]->to_delete = false;
			$nomenclature->TNomenclatureDet[$k]->fk_product = $line['fk_object'];
			
		}
		else if($line['object_type'] == 'workstation') {
			if(empty($nomenclature->TNomenclatureWorkstation[$k])) {
				$nomenclature->TNomenclatureWorkstation[$k]=newTNomenclatureWorkstationTNomenclatureDet;
			}
			$nomenclature->TNomenclatureWorkstation[$k]->to_delete = false;
			$nomenclature->TNomenclatureWorkstation[$k]->fk_workstation = $line['fk_object'];
			
		}
		
		_putHierarchie($PDOdb, empty($line['children']) ? array() : $line['children'],$line['fk_object'],$line['object_type']);
	}
	
	if(isset($nomenclature)
	 && ($nomenclature->TNomenclatureDet!=$nomenclature->TNomenclatureDetOriginal || $nomenclature->TNomenclatureWorkstation!=$nomenclature->TNomenclatureWorkstationOriginal) 
	 ) {
		$nomemclature->save($PDOdb);
	}
	
}
