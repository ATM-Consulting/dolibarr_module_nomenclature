<?php
/* <one line to give the program's name and a brief idea of what it does.>
 * Copyright (C) 2015 ATM Consulting <support@atm-consulting.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * 	\file		core/triggers/interface_99_modMyodule_nomenclaturetrigger.class.php
 * 	\ingroup	nomenclature
 * 	\brief		Sample trigger
 * 	\remarks	You can create other triggers by copying this one
 * 				- File name should be either:
 * 					interface_99_modMymodule_Mytrigger.class.php
 * 					interface_99_all_Mytrigger.class.php
 * 				- The file must stay in core/triggers
 * 				- The class name must be InterfaceMytrigger
 * 				- The constructor method must be named InterfaceMytrigger
 * 				- The name property name must be Mytrigger
 */

/**
 * Trigger class
 */
class Interfacenomenclaturetrigger
{

    private $db;

    /**
     * Constructor
     *
     * 	@param		DoliDB		$db		Database handler
     */
    public function __construct($db)
    {
        $this->db = $db;

        $this->name = preg_replace('/^Interface/i', '', get_class($this));
        $this->family = "demo";
        $this->description = "Triggers of this module are empty functions."
            . "They have no effect."
            . "They are provided for tutorial purpose only.";
        // 'development', 'experimental', 'dolibarr' or version
        $this->version = 'development';
        $this->picto = 'nomenclature@nomenclature';
    }

    /**
     * Trigger name
     *
     * 	@return		string	Name of trigger file
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Trigger description
     *
     * 	@return		string	Description of trigger file
     */
    public function getDesc()
    {
        return $this->description;
    }

    /**
     * Trigger version
     *
     * 	@return		string	Version of trigger file
     */
    public function getVersion()
    {
        global $langs;
        $langs->load("admin");

        if ($this->version == 'development') {
            return $langs->trans("Development");
        } elseif ($this->version == 'experimental')

                return $langs->trans("Experimental");
        elseif ($this->version == 'dolibarr') return DOL_VERSION;
        elseif ($this->version) return $this->version;
        else {
            return $langs->trans("Unknown");
        }
    }

    /**
     * Function called when a Dolibarrr business event is done.
     * All functions "run_trigger" are triggered if file
     * is inside directory core/triggers
     *
     * 	@param		string		$action		Event action code
     * 	@param		Object		$object		Object
     * 	@param		User		$user		Object user
     * 	@param		Translate	$langs		Object langs
     * 	@param		conf		$conf		Object conf
     * 	@return		int						<0 if KO, 0 if no triggered ran, >0 if OK
     */
    public function run_trigger($action, $object, $user, $langs, $conf)
    {
        // Put here code you want to execute when a Dolibarr business events occurs.
        // Data and type of action are stored into $object and $action
        // Users
        
        global $db;
        
        $PDOdb = new TPDOdb;
		
        if ($action == 'ORDER_CREATE') {
			dol_syslog(
                "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
            );
        } elseif ($action == 'LINEORDER_INSERT') {
            
			dol_include_once('/nomenclature/class/nomenclature.class.php');
			
            // Si on vient d'une propal on vérifie s'il existe une nomenclature associée à la propal :
            $origin = GETPOST('origin');
			$origin_id = GETPOST('originid'); // id de la ligne propal
            
			if($origin === 'propal' && !empty($origin_id)) {
				
				if($object->product_type == 0 && $object->fk_product > 0) {
					$sql = 'SELECT rowid
							FROM '.MAIN_DB_PREFIX.'nomenclature
							WHERE object_type = "propal"
							AND fk_nomenclature_parent IN (
								SELECT rowid
								FROM '.MAIN_DB_PREFIX.'nomenclature
								WHERE object_type = "product"
								AND fk_object = '.$object->fk_product.'
							)
							LIMIT 1';
					
					$resql = $db->query($sql);
					$TIDNomenclature = array();
					$res = $db->fetch_object($resql);
					if(!empty($res->rowid)){
						// On charge la nomenclature
						$n = new TNomenclature;
						$n->load($PDOdb, $res->rowid);
						if($n->rowid > 0) {
							// On en crée une nouvelle pour la commande en récupérant toutes les données de l'ancienne
							$n_commande = new TNomenclature;
							$n_commande->fk_nomenclature_parent = $n->rowid;
							$n_commande->object_type = 'commande';
							$n_commande->fk_object = $object->rowid;
							
						    if(!empty($n->TNomenclatureDet)) {
						        foreach($n->TNomenclatureDet as $TDetValues) {
						        	$k = $n_commande->addChild($PDOdb, 'TNomenclatureDet');
						            $n_commande->TNomenclatureDet[$k]->set_values($TDetValues);
						        }
						    }
							if(!empty($n->TNomenclatureWorkstation)) {
							    foreach($n->TNomenclatureWorkstation as $TDetValues) {
							    	
							    	$k = $n_commande->addChild($PDOdb, 'TNomenclatureWorkstation');
							        $n_commande->TNomenclatureWorkstation[$k]->set_values($TDetValues);
							    }
							}
							
							
							$n_commande->save($PDOdb);
							
						}
					}
	
				}

				
			}
            dol_syslog(
                "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
            );
        } elseif ($action == 'ORDER_CLONE') {
            dol_syslog(
                "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
            );
        } 
        
        return 0;
    }
}