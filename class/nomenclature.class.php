<?php

dol_include_once('/workstation/class/workstation.class.php');

class TNomenclature extends TObjetStd
{
    
    function __construct() 
    {
        global $conf; 
        
        $this->set_table(MAIN_DB_PREFIX.'nomenclature');
        $this->add_champs('title');
        $this->add_champs('fk_object,fk_nomenclature_parent',array('type'=>'integer', 'index'=>true));
        $this->add_champs('is_default',array('type'=>'integer', 'index'=>true));
        $this->add_champs('qty_reference',array('type'=>'float','index'=>true));
		
        $this->add_champs('object_type',array('type'=>'string', 'index'=>true));
        $this->add_champs('note_private',array('type'=>'text'));
        
        $this->_init_vars();
        
        $this->start();
		
        $this->setChild('TNomenclatureDet', 'fk_nomenclature');
        if($conf->workstation->enabled) $this->setChild('TNomenclatureWorkstation', 'fk_nomenclature');     
        
        $this->qty_reference = 1;       
        $this->object_type = 'product';
        
        $this->TNomenclatureDet = $this->TNomenclatureDetOriginal = array();
        $this->TNomenclatureWorkstation = $this->TNomenclatureWorkstationOriginal = array();       
        
        $this->iExist = false;
    }   
    
    function reinit() {
        $this->{OBJETSTD_MASTERKEY} = 0; // le champ id est toujours def   
        $this->{OBJETSTD_DATECREATE}=time(); // ces champs dates aussi
        $this->{OBJETSTD_DATEUPDATE}=time();
        
        foreach($this->TNomenclatureDet as &$det) {
            $det->reinit();
        }
        
        foreach($this->TNomenclatureWorkstation as &$det) {
            $det->reinit();
        }
    }
    
	function setPrice(&$PDOdb, $qty_ref, $fk_object, $object_type) {
		
		global $db,$langs,$conf;
		
		if(empty($qty_ref))$coef_qty_price = 1;
		else $coef_qty_price = $qty_ref / $this->qty_reference;
		
	    switch ($object_type) 
        {
           case 'propal':
               	dol_include_once('/comm/propal/class/propal.class.php');
               	dol_include_once('/societe/class/societe.class.php');
				$object = new Propal($db);
	  		 	$object->fetch($fk_object);
				$object->fetch_thirdparty();
				$object_type_string = 'propal';
               	break;
           
        }
		
		$this->TCoefStandard = TNomenclatureCoef::loadCoef($PDOdb);
		if(!empty($object)) $this->TCoefObject = TNomenclatureCoefObject::loadCoefObject($PDOdb, $object, $object_type);
				
		$totalPR = $totalPRC = 0;
		foreach($this->TNomenclatureDet as &$det) {
			
			$det->calculate_price = $det->getSupplierPrice($PDOdb, $det->qty * $coef_qty_price,true) * $det->qty * $coef_qty_price;
			$totalPR+= $det->calculate_price ;
			
			if (!empty($this->TCoefObject[$det->code_type])) $coef = $this->TCoefObject[$det->code_type]->tx_object;
			elseif (!empty($this->TCoefStandard[$det->code_type])) $coef = $this->TCoefStandard[$det->code_type]->tx;
			else $coef = 1;

			$det->charged_price = empty($det->price) ? $det->calculate_price * $coef : $det->price * $coef_qty_price;
			$totalPRC+= $det->charged_price;
		}
		$this->totalPR = $totalPR;
		$this->totalPRC = $totalPRC;
		
		$total_mo = 0;
		foreach($this->TNomenclatureWorkstation as &$ws) {
			$ws->nb_hour_calculate = $ws->nb_hour_prepare + ($ws->nb_hour_manufacture * $coef_qty_price);
			$ws->calculate_price = ($ws->workstation->thm + $ws->workstation->thm_machine) * $ws->nb_hour_calculate;
			$total_mo+=empty($ws->price) ? $ws->calculate_price : $ws->price;
						   	 				
		}
		$this->totalMO = $total_mo;
		
		$marge = TNomenclatureCoefObject::getMarge($PDOdb, $object, $object_type);
		$this->marge = $marge->tx_object;
		
		$this->totalPRCMO = $this->totalMO + $this->totalPRC;
		
		$this->totalPV = $this->totalPRCMO * $marge->tx_object; 
		
		return $coef_qty_price;
	}
	
	function load_original(&$PDOdb, $fk_product=0, $qty=1) {
        
        if(empty($fk_product)) return false;
        
        if($this->fk_nomenclature_parent == 0) {
            $n = TNomenclature::getDefaultNomenclature($PDOdb, $fk_product, $qty);
            if($n === false) return false;
            $this->fk_nomenclature_parent = $n->getId();
        }
	else if($this->fk_nomenclature_parent>0) {
            $n = new TNomenclature;
            $n->load($PDOdb, $this->fk_nomenclature_parent);
        }
//var_dump($n);        
        $this->TNomenclatureDetOriginal = $n->TNomenclatureDet;
        $this->TNomenclatureWorkstationOriginal = $n->TNomenclatureWorkstation;

        if( (count($this->TNomenclatureDet)+count($this->TNomenclatureWorkstation) )==0 && (count($this->TNomenclatureDetOriginal) + count($this->TNomenclatureWorkstationOriginal))>0)
	 {
            foreach($this->TNomenclatureDetOriginal as $k => &$det) {
                $this->TNomenclatureDet[$k] = new TNomenclatureDet;
                $this->TNomenclatureDet[$k]->set_values((array)$det);
            }
            foreach($this->TNomenclatureWorkstationOriginal as $k => &$det) {
                $this->TNomenclatureWorkstation[$k] = new TNomenclatureWorkstation;
                $this->TNomenclatureWorkstation[$k]->set_values((array)$det);
                $this->TNomenclatureWorkstation[$k]->workstation = $det->workstation;
            }
        }
        else{
            $this->iExist = true;
        }
        
        return true;
    }

	function __toString() {
		global $langs;
		
		return '('.$this->getId().') '. ($this->title ? $this->title : $langs->trans('Nomenclature') ) .' : '.$this->qty_reference;
		
	}
	
	function load(&$PDOdb, $id, $loadProductWSifEmpty = false, $fk_product= 0 , $qty = 1) {
		global $conf;
		
		$res = parent::load($PDOdb, $id);
		
		if($loadProductWSifEmpty && $conf->workstation->enabled && empty($this->TNomenclatureWorkstation)) {
			$this->load_product_ws($PDOdb);	
		}
		
		usort($this->TNomenclatureWorkstation, array('TNomenclature', 'sortTNomenclatureWorkstation'));
		
		return $res;
		
	}
	
	function addProduct($PDOdb, $fk_new_product) {
		
		
		$k = $this->addChild($PDOdb, 'TNomenclatureDet');
        $det = &$this->TNomenclatureDet[$k];
        $det->fk_product = $fk_new_product;
		
		$this->save($PDOdb);
		
		if(!$this->infinitLoop($PDOdb)) {			
			return true;
		}
		else {
			
			$det->to_delete = true;
			$this->save($PDOdb);
			
			return false;
		}
	}
	
	function infinitLoop(&$PDOdb, $level = 1) {
		global $conf;
		return false;
		$max_level = empty($conf->global->NOMENCLATURE_MAX_NESTED_LEVEL) ? 50 : $conf->global->NOMENCLATURE_MAX_NESTED_LEVEL;
		if($level > $max_level) return true;
		
		foreach($this->TNomenclatureDet as &$det) {
			
			$fk_product = $det->fk_product;
			
			$n=new TNomenclature;
			$n->loadByObjectId($PDOdb, $fk_product, 'product');
			
			$res = $n->infinitLoop($PDOdb, $level + 1 );
			
			if($res) return true;
			
		}
		
		return false;
	}
	
	function sortTNomenclatureWorkstation(&$objA, &$objB)
	{
		$r = $objA->rang > $objB->rang;
		
		if ($r == 1) return 1;
		else return -1;
	}
	
	function loadByObjectId(&$PDOdb, $fk_object, $object_type, $loadProductWSifEmpty = false, $fk_product = 0, $qty = 1) {
	    $sql = "SELECT rowid FROM ".$this->get_table()." 
            WHERE fk_object=".(int)$fk_object." AND object_type='".$object_type."'";
          
        $PDOdb->Execute($sql);
            
        $res = false;
        if($obj = $PDOdb->Get_line()) {
            $res = $this->load($PDOdb, $obj->rowid, $loadProductWSifEmpty);
            if($res) $this->iExist = true;
        }
        
        $this->load_original($PDOdb, $fk_product, $qty);
        
        return $res; 
            
    }
	
	function deleteChildrenNotImported(&$PDOdb)
	{
		foreach ($this->TNomenclatureDet as $k => &$det)
		{
			if ($det->is_imported > 0) $det->delete($PDOdb);
		}
	}
	
	function load_product_ws(&$PDOdb) {
		
		$this->TNomenclatureWorkstation=array();
		
		$sql = "SELECT fk_workstation, nb_hour,nb_hour_prepare,nb_hour_manufacture";
		$sql.= " FROM ".MAIN_DB_PREFIX."workstation_product";
		$sql.= " WHERE fk_product = ".$fk_product;
		$PDOdb->Execute($sql);
		
		while($res = $PDOdb->Get_line()) 
		{
			$ws = new TWorkstation;
			$ws->load($PDOdb, $res->fk_workstation);
			$k = $this->addChild($PDOdb, 'TNomenclatureWorkstation');
			
			$this->TNomenclatureWorkstation[$k]->fk_workstation = $ws->getId();
			$this->TNomenclatureWorkstation[$k]->nb_hour = $res->nb_hour;
			$this->TNomenclatureWorkstation[$k]->nb_hour_prepare = $res->nb_hour_prepare;
			$this->TNomenclatureWorkstation[$k]->nb_hour_manufacture = $res->nb_hour_manufacture;
			$this->TNomenclatureWorkstation[$k]->workstation = $ws;
			
		}
	}
	
    function getDetails($qty_ref = 1) {
        
        $Tab = array();
		
        $PDOdb = new TPDOdb;
        
        $coef = 1;
        if($qty_ref != $this->qty_reference) {
            $coef = $qty_ref / $this->qty_reference;
        }
		
        foreach($this->TNomenclatureDet as &$d) 
        {
            $qty = $d->qty * $coef;
            $fk_product = $d->fk_product;
			
            $childs = array();
			
			$nomenclature = TNomenclatureDet::getArboNomenclatureDet($PDOdb, $d, $qty);
			
			if(!empty($nomenclature)) $childs = $nomenclature->getDetails($qty);
			
            $Tab[]=array(
                0=>$fk_product
                ,1=>$qty
                ,'fk_product'=>$fk_product
                ,'qty'=>$qty
				,'childs'=>$childs
                ,'note_private'=>$d->note_private
            );
            
        }
        
        return $Tab;
        
    }
    static function get(&$PDOdb, $fk_object, $forCombo=false, $object_type= 'product') 
    {
    	global $langs;
		
        $Tab = $PDOdb->ExecuteAsArray("SELECT rowid FROM ".MAIN_DB_PREFIX."nomenclature 
                WHERE fk_object=".(int) $fk_object." AND object_type='".$object_type."'");
        $TNom=array();
        
        foreach($Tab as $row) {
            
            $n=new TNomenclature;
            $n->load($PDOdb, $row->rowid);
            
			if($forCombo) {
				 $TNom[$n->getId()] = '('.$n->getId().') '. ($n->title ? $n->title : $langs->trans('NoTitle') ) .' : '.$n->qty_reference;
			}
			else{
				 $TNom[] = $n;
			}
           
            
        }
        
        return $TNom;
    }
	
	/*
	 * Return the default TNomenclature object of product or the first of list if not default or false
	 */
	static function getDefaultNomenclature(&$PDOdb, $fk_product, $qty_ref = 0)
	{
		$TNomenclature = new TNomenclature;
		
		$PDOdb->Execute('SELECT rowid FROM '.MAIN_DB_PREFIX.'nomenclature 
		          WHERE (fk_object='.(int)$fk_product.' AND object_type=\'product\')
		          AND qty_reference<='.$qty_ref.'
		          ORDER BY is_default DESC, qty_reference DESC
		          LIMIT 1');
	        $res = $PDOdb->Get_line();
	
		if ($res)
		{
			$TNomenclature->load($PDOdb, $res->rowid);
			
			return $TNomenclature;
		}
		else 
		{
			$Tab = self::get($PDOdb, $fk_product);
			
			if (count($Tab) > 0)
			{
				return $Tab[0];
			}
		}
		
		return false;
	}
	
    static function resetDefaultNomenclature(&$PDOdb, $fk_product)
	{
		return $PDOdb->Execute('UPDATE '.MAIN_DB_PREFIX.'nomenclature SET is_default = 0 WHERE fk_product = '.(int) $fk_product);
	}
	
	/**
	 * @return array : retourne un tableau contenant en clef le fk_product et en valeur le type de ce produit dans la nomenclature
	 */
	function getArrayTypesProducts() {
		$PDOdb = new TPDOdb;
		 
		$TTypesProducts = array();
		$types = TNomenclatureDet::getTType($PDOdb);
		
		foreach ($this->TNomenclatureDet as $key => $value) {
			$TTypesProducts[$value->fk_product] = $types[$value->code_type];
		}

		return $TTypesProducts;
		
	}
	
	function isWorkstationAssociated($fk_new_workstation) {
		
		global $langs;
		
		if(empty($this->TNomenclatureWorkstation)) return false;
		
		foreach ($this->TNomenclatureWorkstation as $ws) {
			if($ws->fk_workstation == $fk_new_workstation) {
				setEventMessage($langs->trans('WorkstationAlreadyAssociated'), 'errors');
				return true;
			}
		}
		
		return false;
		
	}
    
}


class TNomenclatureDet extends TObjetStd
{
    
    
	/**
	 * product_type == fk_coef (rowid de la table nomenclature_coef)
	 */
    function __construct()
    {
        $this->set_table(MAIN_DB_PREFIX.'nomenclaturedet');
		$this->add_champs('title'); //Pour ligne libre
        $this->add_champs('fk_product,fk_nomenclature,is_imported',array('type'=>'integer', 'index'=>true));
		$this->add_champs('code_type',array('type'=>'varchar', 'length' => 30));
        $this->add_champs('qty,price',array('type'=>'float'));
        $this->add_champs('note_private',array('type'=>'text'));
        
        $this->_init_vars();
        
        $this->start();
        
		$this->calculate_price = 0;
		
        $this->qty=1;
        $this->code_type = TNomenclatureCoef::getFirstCodeType();
    }
	
    function reinit() {
        $this->{OBJETSTD_MASTERKEY} = 0; // le champ id est toujours def   
        $this->{OBJETSTD_DATECREATE}=time(); // ces champs dates aussi
        $this->{OBJETSTD_DATEUPDATE}=time();
        
    }
    
	/*
	 * Retourne le prix unitaire en fonction de la quantité commandé
	 */
    function getSupplierPrice(&$PDOdb, $qty = 1, $searchforhigherqtyifnone=false, $search_child_price=true) {
        global $db;
        $PDOdb->Execute("SELECT rowid, price, quantity FROM ".MAIN_DB_PREFIX."product_fournisseur_price 
                WHERE fk_product = ". $this->fk_product." AND quantity<=".$qty." ORDER BY quantity DESC LIMIT 1 ");
     
        if($obj = $PDOdb->Get_line()) {
            $price = $obj->price / $obj->quantity;
            
            return $price;
            
        }
        
        if($searchforhigherqtyifnone) {
            
            $PDOdb->Execute("SELECT rowid, price, quantity FROM ".MAIN_DB_PREFIX."product_fournisseur_price 
                    WHERE fk_product = ". $this->fk_product." AND quantity>".$qty." ORDER BY quantity ASC LIMIT 1 ");
         
            if($obj = $PDOdb->Get_line()) {
                $price = $obj->price / $obj->quantity;
                
                return $price;
            }            
            
        }
		
		if($search_child_price) {
			
			$n = self::getArboNomenclatureDet($PDOdb, $this,$this->qty,false);
			$n->setPrice($PDOdb, $qty, $this->fk_product, 'product');
			
			return $n->totalPRCMO;
		}
        
        return 0;
        
        
    }
	
	//renvoi la nomenclature par defaut du produit de la ligne
	static function getArboNomenclatureDet(&$PDOdb, &$nomenclatureDet, $qty_to_make, $recursive = false) 
	{
		//$defaultNomenclature = self::getDefaultNomenclature($PDOdb, $nomenclatureDet->fk_product, $qty_to_make);
		return TNomenclature::getDefaultNomenclature($PDOdb, $nomenclatureDet->fk_product, $qty_to_make);
	}
	
	
    static function getTType(&$PDOdb, $blankRow = false)
	{
		$res = array();
		if ($blankRow) $res = array('' => '');
		
		$sql = 'SELECT * FROM '.MAIN_DB_PREFIX.'nomenclature_coef ORDER BY rowid';
		$resql = $PDOdb->Execute($sql);
		
		if ($resql && $PDOdb->Get_Recordcount() > 0)
		{
			while ($row = $PDOdb->Get_line())
			{
				if ($row->code_type != 'coef_marge') $res[$row->code_type] = $row->label;
			}
		}
		
		return $res;
	}
}


class TNomenclatureWorkstation extends TObjetStd
{
	
    function __construct() 
    {
        $this->set_table(MAIN_DB_PREFIX.'nomenclature_workstation');
        $this->add_champs('fk_workstation,fk_nomenclature',array('type'=>'integer', 'index'=>true));
        $this->add_champs('nb_hour,nb_hour_prepare,nb_hour_manufacture',array('type'=>'float'));
        $this->add_champs('rang',array('type'=>'float', 'index'=>true));
        $this->add_champs('note_private',array('type'=>'text'));
		$this->add_champs('code_type',array('type'=>'varchar', 'length' => 30));
        
        $this->_init_vars();
        
        $this->start();
        
        $this->qty=1;
        $this->product_type=1;
    }  
	 
    function reinit() 
    {
        $this->{OBJETSTD_MASTERKEY} = 0; // le champ id est toujours def   
        $this->{OBJETSTD_DATECREATE}=time(); // ces champs dates aussi
        $this->{OBJETSTD_DATEUPDATE}=time();
        
    }
	
    function load(&$PDOdb, $id, $annexe = true) 
    {
        parent::load($PDOdb, $id);
        
        if($annexe) {
            $this->workstation = new TWorkstation;
            $this->workstation->load($PDOdb, $this->fk_workstation);    
        }
    }
	
    function save(&$PDOdb) 
    {
        $this->nb_hour  = $this->nb_hour_prepare+$this->nb_hour_manufacture;
        parent::save($PDOdb);    
    }
    
}

class TNomenclatureCoef extends TObjetStd
{
    function __construct() 
    {
        $this->set_table(MAIN_DB_PREFIX.'nomenclature_coef');
        $this->add_champs('label,description',array('type'=>'varchar', 'length'=>255));
		$this->add_champs('code_type',array('type'=>'varchar', 'length'=>30, 'index'=>true));
        $this->add_champs('tx',array('type'=>'float'));
        
        $this->_init_vars();
        
        $this->start();
    }  
	
	function load(&$PDOdb, $id)
	{
		parent::load($PDOdb, $id);
		$this->tx_object = $this->tx;
	}
	
	static function loadCoef(&$PDOdb) 
	{
		$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'nomenclature_coef ORDER BY rowid';
		$TRes = $PDOdb->ExecuteAsArray($sql);
		$TResult = array();
		
		foreach ($TRes as $res)
		{
			$o=new TNomenclatureCoef;
			$o->load($PDOdb, $res->rowid);
			
			$TResult[$o->code_type] = $o;
		}
		
		return $TResult;
	}
	
	static function getFirstCodeType(&$PDOdb = false)
	{
		if (!$PDOdb) $PDOdb = new TPDOdb;
		
		$resql = $PDOdb->Execute('SELECT MIN(rowid) AS rowid, code_type FROM '.MAIN_DB_PREFIX.'nomenclature_coef');
		if ($resql && $PDOdb->Get_Recordcount() > 0)
		{
			$row = $PDOdb->Get_line();
			return $row->code_type;
		}
		
		return null;
	}
	
    function save(&$PDOdb) 
    {
    	$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'nomenclature_coef WHERE code_type = '.$PDOdb->quote($this->code_type).' AND rowid <> '.(int) $this->getId();
		$res = $PDOdb->Execute($sql);
		
		if ($res && $PDOdb->Get_Recordcount() > 0)
		{
			return 0;
		}
        
		$rowid = parent::save($PDOdb);
		return $rowid;
    }
    
	function delete(&$PDOdb)
	{
		if ($this->code_type == 'coef_marge') return false;
		
		//Vérification que le coef ne soit pas utilisé - si utilisé alors on interdit la suppression	
		$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'nomenclaturedet WHERE code_type = '.$this->code_type.' 
				UNION
				SELECT rowid FROM '.MAIN_DB_PREFIX.'nomenclature_coef_object WHERE code_type = '.$this->code_type;
		
		$res = $PDOdb->ExecuteAsArray($sql);
		
		if (count($res) > 0)
		{
			return 0;
		}
		
		parent::delete($PDOdb);
		return 1;
	}
	
}

class TNomenclatureCoefObject extends TObjetStd
{
	function __construct() 
    {
        $this->set_table(MAIN_DB_PREFIX.'nomenclature_coef_object');
		
		$this->add_champs('fk_object',array('type'=>'integer', 'index'=>true));
        $this->add_champs('type_object',array('type'=>'varchar', 'length'=>50, 'index'=>true));
		$this->add_champs('code_type',array('type'=>'vachar', 'length'=>30, 'index'=>true));
        $this->add_champs('tx_object',array('type'=>'float'));
        
        $this->_init_vars();
        
        $this->start();
    }
	
	function loadByTypeByCoef(&$PDOdb, $code_type, $fk_object, $type_object) 
	{
		$PDOdb->Execute("SELECT rowid FROM ".$this->get_table()." WHERE code_type='".$code_type."' AND fk_object=".$fk_object." AND type_object='".$type_object."'");
		
		if($obj = $PDOdb->Get_line()) 
		{
			return $this->load($PDOdb, $obj->rowid);
		}

		return false;
	}

	static function getMarge(&$PDOdb, $object, $type_object)
	{
		$TCoef = self::loadCoefObject($PDOdb, $object, $type_object);
		$marge = $TCoef['coef_marge'];
		
		if($marge > 5) $marge = 1+($marge/100);
		
		return $marge;
	}

	static function loadCoefObject(&$PDOdb, &$object, $type_object, $fk_origin=0)
	{
		$Tab = array();
		$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'nomenclature_coef_object WHERE fk_object = '.(int)$object->id.' AND type_object = "'.$type_object.'"';
		$PDOdb->Execute($sql);
		$TRes = $PDOdb->Get_All();
		
		switch ($type_object) {
			case 'propal':
				$TCoef = self::loadCoefObject($PDOdb, $object->thirdparty, 'tiers', $object->id); // Récup des coefs du parent (exemple avec propal -> je charge les coef du tiers associé)
				break;
			default:
				$TCoef = TNomenclatureCoef::loadCoef($PDOdb);
				break;
		}
		
		foreach ($TRes as $row)
		{
			$o = new TNomenclatureCoefObject;
			$o->load($PDOdb, $row->rowid);
			$o->fk_origin = $fk_origin;
			$o->tx = $o->tx_object;
			
			$Tab[$o->code_type] = $o;
		}
		
		foreach($TCoef as $k=> &$coef) {
			
			$coef->rowid = 0;
			$coef->tx_object = $coef->tx;
			
			if(!isset($Tab[$k])) $Tab[$k] = $coef;
			else {
				$Tab[$k]->label = $coef->label;
				$Tab[$k]->description = $coef->description;
				
		 	}
		}
		
		ksort($Tab);
		return $Tab;
	}
}
