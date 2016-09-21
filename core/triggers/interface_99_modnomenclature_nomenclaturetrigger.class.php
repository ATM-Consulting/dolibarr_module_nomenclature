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
	 * @param string $action Event action code
	 * @param Object $object Object
	 * @param User $user Object user
	 * @param Translate $langs Object langs
	 * @param conf $conf Object conf
	 * @return int <0 if KO, 0 if no triggered ran, >0 if OK
	 */
	public function run_trigger($action, $object, $user, $langs, $conf) {
		// Put here code you want to execute when a Dolibarr business events occurs.
		// Data and type of action are stored into $object and $action
		// Users
		global $db, $conf;

		define('INC_FROM_DOLIBARR', true);
		dol_include_once('/nomenclature/config.php');
		dol_include_once('/nomenclature/class/nomenclature.class.php');
		$PDOdb = new TPDOdb();

		if ($action == 'ORDER_CREATE') {
			dol_syslog("Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id);
		} elseif ($action == 'LINEPROPAL_INSERT') {
			$this->_setPrice($PDOdb, $object, $object->fk_propal, 'propal');
		} elseif ($action == 'LINEBILL_INSERT' && !empty($conf->global->NOMENCLATURE_USE_ON_INVOICE)) {
			$this->_setPrice($PDOdb, $object, $object->fk_facture, 'facture');
		} elseif ($action == 'LINEORDER_INSERT') {

			if (! $conf->nomenclature->enabled)
				return 0;
			if ($object->product_type == 9)
				return 0;

				// Si on vient d'une propal on vérifie s'il existe une nomenclature associée à la propal :
			$origin = GETPOST('origin');
			$origin_id = GETPOST('originid'); // id de la ligne propal <= FAUX, id de la propal d'origin

			if ($origin !== 'propal' || empty($origin_id)) {
				null;
			} else {

				$propal = new Propal($db);
				$propal->fetch($origin_id);
				$fk_line_origin = 0;

				foreach ( $propal->lines as $line ) {
					if ($line->product_type == $object->product_type && $line->qty == $object->qty && $line->desc == $object->desc && $line->fk_product == $object->fk_product && $line->tva_tx == $object->tva_tx && $line->total_ttc == $object->total_ttc) {
						$fk_line_origin = $line->id;
						break;
					}
				}

				// On cherche la nomenclature de type propal, ayant pour parent une nomenclature du produit de la ligne de propal
				$sql = 'SELECT rowid FROM ' . MAIN_DB_PREFIX . 'nomenclature
				WHERE (
					object_type = "propal"
					AND fk_object = ' . $fk_line_origin . '
			  	)
				OR fk_nomenclature_parent IN (
						SELECT rowid
						FROM ' . MAIN_DB_PREFIX . 'nomenclature
						WHERE object_type = "product"
						AND fk_object = ' . ( int ) $object->fk_product . '
						ORDER BY rowid ASC
				)';

				$resql = $db->query($sql);
				$TIDNomenclature = array ();
				$res = $db->fetch_object($resql);
				if (! empty($res->rowid)) {
					// On charge la nomenclature
					$n = new TNomenclature();
					$n->load($PDOdb, $res->rowid);
					if ($n->rowid > 0) {
						// On en crée une nouvelle pour la commande en récupérant toutes les données de l'ancienne
						$n_commande = new TNomenclature();
						$n_commande->fk_nomenclature_parent = $res->rowid;
						$n_commande->object_type = 'commande';
						$n_commande->fk_object = $object->rowid;

						if (! empty($n->TNomenclatureDet)) {
							foreach ( $n->TNomenclatureDet as $TDetValues ) {
								$k = $n_commande->addChild($PDOdb, 'TNomenclatureDet');
								$n_commande->TNomenclatureDet[$k]->set_values($TDetValues);
							}
						}
						if (! empty($n->TNomenclatureWorkstation)) {
							foreach ( $n->TNomenclatureWorkstation as $TDetValues ) {

								$k = $n_commande->addChild($PDOdb, 'TNomenclatureWorkstation');
								$n_commande->TNomenclatureWorkstation[$k]->set_values($TDetValues);
							}
						}

						$n_commande->save($PDOdb);
					}
				}
			}
			$this->_setPrice($PDOdb, $object, $object->fk_commande, 'commande');

			dol_syslog("Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id);
		} elseif ($action === 'PROPAL_CLONE' || $action === 'ORDER_CLONE') {

			if ($action === 'PROPAL_CLONE')
				$origin = 'propal';
			else
				$origin = 'commande';

			$classname = ucfirst($origin);

			// On load l'objet initial :
			$o = new $classname($db);
			$o->fetch(GETPOST('id'));
			$object->fetch($object->id); // Pour recharger les bonnes lignes qui sinon sont celles de l'objet de départ

			if (! empty($o->lines)) {
				foreach ( $o->lines as $i => $line ) {
					$n = new TNomenclature();
					$n->loadByObjectId($PDOdb, $line->rowid, $origin);

					if ($n->rowid > 0) {
						$n_new = new TNomenclature();
						$n_new->fk_nomenclature_parent = $n->fk_nomenclature_parent;
						$n_new->object_type = $origin;
						$n_new->fk_object = $object->lines[$i]->rowid;

						if (! empty($n->TNomenclatureDet)) {
							foreach ( $n->TNomenclatureDet as $TDetValues ) {
								$k = $n_new->addChild($PDOdb, 'TNomenclatureDet');
								$n_new->TNomenclatureDet[$k]->set_values($TDetValues);
							}
						}
						if (! empty($n->TNomenclatureWorkstation)) {
							foreach ( $n->TNomenclatureWorkstation as $TDetValues ) {

								$k = $n_new->addChild($PDOdb, 'TNomenclatureWorkstation');
								$n_new->TNomenclatureWorkstation[$k]->set_values($TDetValues);
							}
						}

						$n_new->save($PDOdb);
					}
				}
			}

			dol_syslog("Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id);
		} elseif ($action == 'COMPANY_DELETE') {
			$sql = 'DELETE FROM ' . MAIN_DB_PREFIX . 'nomenclature_coef_object WHERE fk_object = ' . $object->id . ' AND type_object = "tiers"';
			$db->query($sql);
		} elseif ($action == 'PROPAL_DELETE') {
			$sql = 'DELETE FROM ' . MAIN_DB_PREFIX . 'nomenclature_coef_object WHERE fk_object = ' . $object->id . ' AND type_object = "propal"';
			$db->query($sql);

			$this->_deleteNomenclature($PDOdb, $db, $object, 'propal');
		} elseif ($action == 'ORDER_DELETE') {
			$this->_deleteNomenclature($PDOdb, $db, $object, 'commande');
		}

		return 0;
	}


	private function _setPrice(&$PDOdb, &$object,$fk_parent,$object_type) {
		global $db,$conf,$user,$langs;

		if ($object->subprice >0 
			|| !empty($conf->global->NOMENCLATURE_USE_SELL_PRICE_INSTEADOF_CALC)
			|| $object->product_type>1 ) {
					return 0;
		}

		$n = new TNomenclature;
        	$n->loadByObjectId($PDOdb, $object->id , $object_type, true,$object->fk_product,$object->qty);
		$n->setPrice($PDOdb, $object->qty, $object->id, $object_type);


		if (!empty($conf->global->NOMENCLATURE_USE_SELL_PRICE_INSTEADOF_CALC)) {
			$sell_price_to_use=$object->subprice;
		} else {
			$sell_price_to_use=$n->totalPV;
		}


		if(empty($sell_price_to_use)) return 0;

		if($object_type=='commande') {
//		var_dump($n->totalPV, $object_type,$object);exit;

			$commande = new Commande($db);
			$commande->fetch($fk_parent);

			$commande->updateline($object->id,$object->desc,$sell_price_to_use,$object->qty,$object->remise_percent,$object->txtva,$object->txlocaltax1,$object->txlocaltax2,'HT',0,$object->date_start,$object->date_end,$object->product_type,0,0,$object->fk_fournprice,$n->totalPRCMO);
		}

		else if($object_type=='propal') {
			$propal = new Propal($db);
			$propal->fetch($fk_parent);
			$propal->updateline($object->id,$sell_price_to_use,$object->qty,$object->remise_percent,$object->txtva,$object->txlocaltax1,$object->txlocaltax2,$object->desc,'HT',0,0,0,0,$object->fk_fournprice,$n->totalPRCMO);

		}else if ($object_type == 'facture') {

			$facture = new Facture($db);
			$facture->fetch($fk_parent);
			$facture->updateline($object->id, $object->desc, $sell_price_to_use, $object->qty, $object->remise_percent, $object->date_start, $object->date_end, $object->txtva, $object->txlocaltax1, $object->txlocaltax2, 'HT', 0, $facture->type, 0, 0, $object->fk_fournprice, $n->totalPRC_fruidoraix,'',0,0,100);
		}

	}

	private function _deleteNomenclature(&$PDOdb, &$db, &$object, $object_type)
	{
		foreach ($object->lines as $line)
		{
			if ($line->product_type == 9) continue;
			
			$line_id = (!empty($line->id)?$line->id:$line->rowid);
			$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'nomenclature WHERE object_type = "'.$object_type.'" AND fk_object = '.$line_id;
			
			$PDOdb->Execute($sql);

			if ($PDOdb->Get_Recordcount() > 0)
			{
				$obj = $PDOdb->Get_line();

				$db->query('DELETE FROM '.MAIN_DB_PREFIX.'nomenclature_workstation WHERE fk_nomenclature = '.$obj->rowid);
				$db->query('DELETE FROM '.MAIN_DB_PREFIX.'nomenclaturedet WHERE fk_nomenclature = '.$obj->rowid);
				$db->query('DELETE FROM '.MAIN_DB_PREFIX.'nomenclature WHERE rowid = '.$obj->rowid);
			}
		}

	}

}
