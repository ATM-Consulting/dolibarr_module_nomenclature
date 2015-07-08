<?php

dol_include_once('/workstation/class/workstation.class.php');

class TNomenclature extends TObjetStd
{
    
    function __construct() 
    {
        global $conf; 
        
        $this->set_table(MAIN_DB_PREFIX.'nomenclature');
        $this->add_champs('title');
        $this->add_champs('fk_product',array('type'=>'integer', 'index'=>true));
        $this->add_champs('is_default',array('type'=>'integer', 'index'=>true));
        $this->add_champs('qty_reference',array('type'=>'float','index'=>true));
        
        $this->_init_vars();
        
        $this->start();
		
        $this->setChild('TNomenclatureDet', 'fk_nomenclature');
        if($conf->workstation->enabled) $this->setChild('TNomenclatureWorkstation', 'fk_nomenclature');     
        
        $this->qty_reference = 1;       
        
    }   
	
	function __toString() {
		global $langs;
		
		return '('.$this->getId().') '. ($this->title ? $this->title : $langs->trans('Nomenclature') ) .' : '.$this->qty_reference;
		
	}
	
	function load(&$PDOdb, $id, $loadProductWSifEmpty = false) {
		global $conf;
		
		$res = parent::load($PDOdb, $id);
		
		if($loadProductWSifEmpty && $conf->workstation->enabled && empty($this->TNomenclatureWorkstation)) {
			$this->load_product_ws($PDOdb);	
		}
		
		return $res;
		
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
        
        $coef = 1;
        if($qty_ref != $this->qty_reference) {
            $coef = $qty_ref / $this->qty_reference;
        }
        
        foreach($this->TNomenclatureDet as &$d) {
            
            $qty = $d->qty * $coef;
            $fk_product = $d->fk_product;
            
            $Tab[]=array(
                0=>$fk_product
                ,1=>$qty
                ,'fk_product'=>$fk_product
                ,'qty'=>$qty
            );
            
        }
        
        return $Tab;
        
    }
    static function get(&$PDOdb, $fk_product, $forCombo=false) 
    {
    	global $langs;
		
        $Tab = $PDOdb->ExecuteAsArray("SELECT rowid FROM ".MAIN_DB_PREFIX."nomenclature WHERE fk_product=".(int) $fk_product);
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
		          WHERE fk_product='.(int) $fk_product.' 
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
    
}


class TNomenclatureDet extends TObjetStd
{
    
    static $TType=array(
        1=>'Principal'
        ,2=>'Secondaire'
        ,3=>'Consommable'
    );
    
    function __construct() 
    {
        $this->set_table(MAIN_DB_PREFIX.'nomenclaturedet');
        $this->add_champs('fk_product,product_type,fk_nomenclature',array('type'=>'integer', 'index'=>true));
        $this->add_champs('qty',array('type'=>'float'));
        
        $this->_init_vars();
        
        $this->start();
        
        $this->qty=1;
        $this->product_type=1;        
    }   
    
    function getSupplierPrice(&$PDOdb, $qty = 1) {
        global $db;
        $PDOdb->Execute("SELECT rowid, price, quantity FROM ".MAIN_DB_PREFIX."product_fournisseur_price 
                WHERE fk_product = ". $this->fk_product." AND quantity<=".$qty." ORDER BY quantity DESC LIMIT 1 ");
     
        if($obj = $PDOdb->Get_line()) {
            $price = $obj->price / $obj->quantity * $qty;
            
            return $price;
            
        }        
        
        return 0;
        
        
    }
}


class TNomenclatureLine extends TObjetStd
{
    
    
    function __construct() 
    {
        $this->set_table(MAIN_DB_PREFIX.'nomenclature_line');
        $this->add_champs('fk_nomenclature,fk_object,fk_line',array('type'=>'integer', 'index'=>true));
        $this->add_champs('object_type',array('type'=>'string', 'index'=>true));
        
        $this->_init_vars();
        
        $this->start();
        
        $this->qty=1;
        $this->product_type=1;        

		$this->setChild('TNomenclatureLineDet', 'fk_nomenclature_line');
        if($conf->workstation->enabled) $this->setChild('TNomenclatureLineWorkstation', 'fk_nomenclature_line');     

        $this->TNomenclatureDet = $this->TNomenclatureDetOriginal = array();
        $this->TNomenclatureWorkstation = $this->TNomenclatureWorkstationOriginal = array();
    }   
    
    function load(&$PDOdb, $id) {
    			
    	$res = parent::load($PDOdb, $id);
    	$this->load_original($PDOdb);
    	
    	return $res;	
    	
    }
	
	function load_original(&$PDOdb) {
		
		$n = new TNomenclature;
		$n->load($PDOdb, $this->fk_nomenclature);
		
		$this->TNomenclatureDetOriginal = $n->TNomenclatureDet;
		$this->TNomenclatureWorkstationOriginal = $n->TNomenclatureWorkstation;
		
		
	}

}
class TNomenclatureLineDet extends TObjetStd
{
    
    function __construct() 
    {
        $this->set_table(MAIN_DB_PREFIX.'nomenclature_line_det');
        $this->add_champs('fk_product,product_type,fk_nomenclature_line',array('type'=>'integer', 'index'=>true));
        $this->add_champs('qty',array('type'=>'float'));
        
        $this->_init_vars();
        
        $this->start();
        
        $this->qty=1;
        $this->product_type=1;
		
                
    }   
    
}

class TNomenclatureWorkstation extends TObjetStd
{
    
    
    function __construct() 
    {
        $this->set_table(MAIN_DB_PREFIX.'nomenclature_workstation');
        $this->add_champs('fk_workstation,fk_nomenclature,rang',array('type'=>'integer', 'index'=>true));
        $this->add_champs('nb_hour,nb_hour_prepare,nb_hour_manufacture',array('type'=>'float'));
        
        $this->_init_vars();
        
        $this->start();
        
        $this->qty=1;
        $this->product_type=1;        
    }   
    function load(&$PDOdb, $id, $annexe = true) {
        parent::load($PDOdb, $id);
        
        if($annexe) {
            $this->workstation = new TWorkstation;
            $this->workstation->load($PDOdb, $this->fk_workstation);    
        }
         
        
    }
    function save(&$PDOdb) {
        
        $this->nb_hour  = $this->nb_hour_prepare+$this->nb_hour_manufacture;
        
        parent::save($PDOdb);    
    }
    
}

class TNomenclatureLineWorkstation extends TObjetStd
{
    
    
    function __construct() 
    {
        $this->set_table(MAIN_DB_PREFIX.'nomenclature_line_workstation');
        $this->add_champs('fk_workstation,fk_nomenclature_line,rang',array('type'=>'integer', 'index'=>true));
        $this->add_champs('nb_hour,nb_hour_prepare,nb_hour_manufacture',array('type'=>'float'));
        
        $this->_init_vars();
        
        $this->start();
        
        $this->qty=1;
        $this->product_type=1;        
    }   
    function load(&$PDOdb, $id, $annexe = true) {
        parent::load($PDOdb, $id);
        
        if($annexe) {
            $this->workstation = new TWorkstation;
            $this->workstation->load($PDOdb, $this->fk_workstation);    
        }
         
        
    }
    function save(&$PDOdb) {
        $this->nb_hour  = $this->nb_hour_prepare+$this->nb_hour_manufacture;
        
        parent::save($PDOdb);    
    }
    
}
