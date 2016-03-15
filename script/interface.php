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
			
			_putHierarchie($PDOdb,GETPOST('THierarchie'),GETPOST('fk_object'),GETPOST('object_type'));
			
			break;
		case 'rang':
			
			_setRang($PDOdb, GETPOST('TRank'),GETPOST('type'));
			
			break;
    }
    
}

function _setRang(&$PDOdb, $TRank,$type) {
	
	foreach($TRank as $k=>$id) {
		
		if($type == 'det') $o=new TNomenclatureDet;
		else $o=new TNomenclatureWorkstation;
		
		$o->load($PDOdb, $id);
		$o->rang = $k;
		if(empty($o->unifyRang)) $o->unifyRang = $o->rang;
		
		$o->save($PDOdb);
		
	}
	
}

function _get_nomenclature_line() {
	
	
	
}

function _putHierarchieNomenclature(&$PDOdb, $THierarchie,$fk_object=0,$object_type='') {
	
	print 'HIERARCHIE ('.$fk_object.','.$object_type.')<br />';
	
	if($object_type!='') {
		$nomenclature=new TNomenclature;
		if(!$nomenclature->loadByObjectId($PDOdb, $fk_object, $object_type)) {
			$nomenclature->fk_object = $fk_object;
			$nomenclature->object_type = $object_type;
		}
	}
	
	if(!empty($nomenclature->TNomenclatureDet)) {
		foreach($nomenclature->TNomenclatureDet as &$det) {
			$det->to_delete = true;
		}
	}
	if(!empty($nomenclature->TNomenclatureWorkstation)) {
		foreach($nomenclature->TNomenclatureWorkstation as &$det) {
			$det->to_delete = true;
		}
	}
	

	foreach($THierarchie as $kUnify=>&$line) {
		
		if(!empty($line['dontuse'])) return false; // ne pas traiter
		
		$k = $line['order'];	
		if($line['object_type'] == 'product') {
			if(empty($nomenclature->TNomenclatureDet[$k])) {
				$nomenclature->TNomenclatureDet[$k]=new TNomenclatureDet;
				
			}
			$nomenclature->TNomenclatureDet[$k]->to_delete = false;
			$nomenclature->TNomenclatureDet[$k]->fk_product = $line['fk_object'];
			$nomenclature->TNomenclatureDet[$k]->unifyRang = $kUnify;
			//print $nomenclature->getid()."/$kUnify det <br />";
			if(isset($line['qty'])) $nomenclature->TNomenclatureDet[$k]->qty = $line['qty'];
			
		}
		else if($line['object_type'] == 'workstation') {
			if(empty($nomenclature->TNomenclatureWorkstation[$k])) {
				$nomenclature->TNomenclatureWorkstation[$k]=new TNomenclatureWorkstation;
			}
			$nomenclature->TNomenclatureWorkstation[$k]->to_delete = false;
			$nomenclature->TNomenclatureWorkstation[$k]->fk_workstation = $line['fk_object'];
			$nomenclature->TNomenclatureWorkstation[$k]->unifyRang = $kUnify;
			//print $nomenclature->getid()."/$kUnify ws <br />";
			if(isset($line['nb_hour_manufacture'])) {
				$nomenclature->TNomenclatureWorkstation[$k]->nb_hour_manufacture = $line['nb_hour_manufacture'];
				$nomenclature->TNomenclatureWorkstation[$k]->nb_hour_prepare = $line['nb_hour_prepare'];
			} 
			
		}

		if($line['object_type'] != 'workstation') _putHierarchieNomenclature($PDOdb, empty($line['children']) ? array() : $line['children'],$line['fk_object'],$line['object_type']);

	}
	/*if($fk_object == 11) {
		var_dump($THierarchie,$nomenclature);exit;
		
		
	}*/
	
	if(isset($nomenclature)
	 && ($nomenclature->TNomenclatureDet!=$nomenclature->TNomenclatureDetOriginal || $nomenclature->TNomenclatureWorkstation!=$nomenclature->TNomenclatureWorkstationOriginal) 
	 ) {
	 /*	if($fk_object == 3 && $object_type=='product') {
		var_dump($nomenclature->TNomenclatureDet!=$nomenclature->TNomenclatureDetOriginal);
		
		var_dump($nomenclature);
	}*/
	//	$PDOdb->debug=true;
	 	$nomenclature->save($PDOdb);
	//	$PDOdb->debug=false;
	}
}

function _putHierarchie(&$PDOdb, $THierarchie,$fk_object=0,$object_type='') {
	global $db;
	
	dol_include_once('/comm/propal/class/propal.class.php');
	dol_include_once('/commande/class/commande.class.php');
	
	if($object_type=='propal') $o=new Propal($db);
	else if($object_type=='commande') $o=new Commande($db);	
	
	$o->fetch($fk_object);
	
	foreach($THierarchie as &$line) {
		
		$type = $line['object_type'];
		$fk_line = $line['fk_object'];
		$order = $line['order'];
		
		if($type=='propal') $table = 'propaldet';
		else if($type=='commande') $table = 'commandedet';
		
		foreach($o->lines as &$l) {

			if($l->id == $fk_line) {
				$o->updateRangOfLine($fk_line, $order);
				print 'UPDATE line-rank('.$fk_line.') : '.$order.'</br >';
				// propal, TODO commande
				if($l->product_type == 9) {
					null;					
				}
				else {
					$o->updateline($fk_line, $l->subprice, $line['qty'], $l->remise_percent, $l->tva_tx, $l->localtax1_tx, $l->localtax2_tx, $l->desc, 'HT'
					, $l->info_bits, $l->special_code, $l->fk_parent_line, 0, $l->fk_fournprice, $l->pa_ht, $l->label, $l->product_type, $l->date_start
					, $l->date_end, $l->array_options, $l->fk_unit);					
					
				}
			}			
			
		}
		
	}
	
	
	_putHierarchieNomenclature($PDOdb,$THierarchie,$fk_object,$object_type);
	
	
}
