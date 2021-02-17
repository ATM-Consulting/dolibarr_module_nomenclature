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

		if (!defined('INC_FROM_DOLIBARR')) define('INC_FROM_DOLIBARR', true);
		dol_include_once('/nomenclature/config.php');
		dol_include_once('/nomenclature/class/nomenclature.class.php');
		$PDOdb = new TPDOdb();

		if($conf->subtotal->enabled) {
			dol_include_once('/subtotal/class/subtotal.class.php');
			if (isset($object->element) && strpos($object->element, 'det') !== false && TSubtotal::isModSubtotalLine($object)) return 0;
		}
		// MAJ de la quantité de fabrication si issue d'une nomenclature non sécable
        if ($action === 'ASSET_LINE_OF_SAVE' && $object->type === 'TO_MAKE')
        {
            if (!empty($object->fk_commandedet)) $object_type = 'commande';
            else $object_type = 'product';

            $n = new TNomenclature;
            if (
                $n->loadByObjectId($PDOdb, $object->fk_product, $object_type, false)
                && $n->getId() > 0
                && $n->non_secable
            )
            {
                $qyt_reference = $n->qty_reference;
                while ($qyt_reference < $object->qty_needed)
                {
                    $qyt_reference+= $n->qty_reference;
                }

                $object->qty_needed = $qyt_reference;
                $object->qty = $qyt_reference;
            }

            /** @var TAssetOFLine $object */
            $object->saveQty($PDOdb);
        }
		elseif ($action == 'LINEPROPAL_INSERT') {
            $this->_insertNomenclatureAndSetPrice($PDOdb, $object);
		} elseif ($action == 'LINEBILL_INSERT' && !empty($conf->global->NOMENCLATURE_USE_ON_INVOICE)) {
			$this->_setPrice($PDOdb, $object, $object->fk_facture, 'facture');
		} elseif ($action == 'LINEORDER_INSERT') {

			if (empty($conf->nomenclature->enabled) || $object->product_type == 9)	return 0;

			// Si on vient d'une propal on vérifie s'il existe une nomenclature associée à la propal :
			$origin = GETPOST('origin', 'none');
			$origin_id = GETPOST('originid', 'int'); // id de la ligne propal <= FAUX, id de la propal d'origin

			// Module Workflow
			if(empty($origin) && empty($origin_id) && ! empty($object->context['origin']) && ! empty($object->context['origin_id'])) {
				$origin = $object->context['origin'];
				$origin_id = $object->context['origin_id'];
			}

			if ($origin !== 'propal' || empty($origin_id)) {
                $this->_insertNomenclatureAndSetPrice($PDOdb, $object);
			} else {

				$propal = new Propal($db);
				$propal->fetch($origin_id);
				$fk_line_origin = 0;
				foreach ( $propal->lines as $line ) {
					if ($line->rang == $object->rang) {
						$fk_line_origin = $line->id;
						$line_origin = $line;
						break;
					}
				}

				if (!empty($line_origin))
				{
					$n = new TNomenclature();
					$n->loadByObjectId($PDOdb, $line_origin->id, $propal->element,true, $line_origin->fk_product, $line_origin->qty, $propal->id, true);

					if ($n->getId() > 0 || $n->fk_nomenclature_parent > 0)
					{
						if ($n->getId() == 0) $need_set_price = true;
						else $need_set_price = false;

						$n->fk_object = $object->id;
						$n->object_type = 'commande'; // pas commandedet !
						$n->cloneObject($PDOdb);
						if ($need_set_price)
						{
							$n->setPrice($PDOdb, $this->qty_reference, $this->fk_object, $this->object_type, $object->fk_commande);
							$n->save($PDOdb);
						}
					}
				}

			}
			$this->_setPrice($PDOdb, $object, $object->fk_commande, 'commande');

			dol_syslog("Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id);
		}
		elseif ((floatval(DOL_VERSION) <= 7.0 && in_array($action, array('PROPAL_CLONE', 'ORDER_CLONE'))) ||
                (floatval(DOL_VERSION) >= 8.0 && ! empty($object->context) && in_array('createfromclone', $object->context) && in_array($action, array('PROPAL_CREATE', 'ORDER_CREATE')))) {
            /**
             * A partir de la version 8.0 de Dolibarr, les Triggers "*_CLONE" ont été supprimés
             * Dans les Triggers "*_CREATE", il faut se fier à $object->context pour savoir si c'est un clone ou pas...
             */
            $TOrigin = explode('_', $action);
			if ($TOrigin[0] == 'PROPAL')
				$origin = 'propal';
			else
				$origin = 'commande';

			$classname = ucfirst($origin);

			// On load l'objet initial :
			$o = new $classname($db);
			$o->fetch(GETPOST('id', 'int'));
			$object->fetch($object->id); // Pour recharger les bonnes lignes qui sinon sont celles de l'objet de départ

			if ($origin == 'propal') {
				$TCoeffPropal = TNomenclatureCoefObject::loadCoefObject($PDOdb, $o, 'propal');

				foreach ($TCoeffPropal as &$coeffObject) {
					$coeffObject->rowid = 0;
					$coeffObject->fk_object = $object->id;
					$coeffObject->save($PDOdb);
				}
			}

			dol_syslog("Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id);
		} elseif ($action == 'COMPANY_DELETE') {
			$sql = 'DELETE FROM ' . MAIN_DB_PREFIX . 'nomenclature_coef_object WHERE fk_object = ' . $object->id . ' AND type_object = "tiers"';
			$db->query($sql);
		} elseif ($action == 'PROPAL_DELETE') {

			$this->_deleteNomenclature($PDOdb, $db, $object, 'propal');

			TNomenclatureWorkstationThmObject::deleteAllThmObject($PDOdb, $object->id, $object->element);

			TNomenclatureCoefObject::deleteCoefsObject($PDOdb, $object->id, $object->element);

		} elseif ($action == 'ORDER_DELETE') {
			$this->_deleteNomenclature($PDOdb, $db, $object, 'commande');
		} elseif ($action == 'PRODUCT_DELETE') {
			$n = new TNomenclature();
			$n->loadByObjectId($PDOdb, $object->id, $object->element);
			$n->delete($PDOdb);
		} elseif ($action == 'LINEPROPAL_DELETE' && $object->element == 'propaldet') {
			$n = new TNomenclature();
			$n->loadByObjectId($PDOdb, $object->id, 'propal');
			$n->delete($PDOdb);
		}
		elseif ($action == 'LINEORDER_DELETE' && $object->element == 'commandedet')
        {
            $n = new TNomenclature();
            $n->loadByObjectId($PDOdb, $object->id, 'commande');
            $n->delete($PDOdb);
        }
		elseif ($action == 'LINE_DUPLICATE') {

			if ($object->line_from->product_type != 9)
			{
				$n = new TNomenclature;
				$n->loadByObjectId($PDOdb, $object->line_from->id, $object->element, true, $object->line_from->fk_product, $object->line_from->qty);

				// S'il y a bien un load depuis ma ligne de propal d'origine
				if ($n->iExist)
				{
					$TAttributesToCopy = array('title', 'fk_nomenclature_parent', 'is_default', 'qty_reference', 'note_private', 'non_secable');

					$n_new = new TNomenclature();
					$n_new->loadByObjectId($PDOdb, $object->line->id, $object->element, true, $object->line_from->fk_product, $object->line_from->qty);

					foreach ($TAttributesToCopy as $attribute)
					{
						$n_new->{ $attribute } = $n->{ $attribute };
					}

					if (! empty($n->TNomenclatureDet)) {
						foreach ( $n->TNomenclatureDet as $TDetValues ) {
							$k = $n_new->addChild($PDOdb, 'TNomenclatureDet');
							$n_new->TNomenclatureDet[$k]->set_values($TDetValues);
							$n_new->TNomenclatureDet[$k]->fk_origin = $TDetValues->rowid;
						}
					}
					if (! empty($n->TNomenclatureWorkstation)) {
						foreach ( $n->TNomenclatureWorkstation as $TDetValues ) {

							$k = $n_new->addChild($PDOdb, 'TNomenclatureWorkstation');
							$n_new->TNomenclatureWorkstation[$k]->set_values($TDetValues);
						}
					}

					$n_new->setPrice($PDOdb, $object->line->qty, $object->id, $object->element);

					$n_new->save($PDOdb);

					$this->_setPrice($PDOdb, $object->line, $object->id, $object->element);
				}
			}

		} elseif($action == 'LINEPROPAL_UPDATE') {
			// récupération du prix calculé :
			$pv_force = false;
			$n = new TNomenclature;
			$n->loadByObjectId($PDOdb, $object->id , 'propal', true,$object->fk_product,$object->qty);

			$id = $n->getId();
			if (!empty($id) || !empty($n->fk_nomenclature_parent)) {

				$n->setPrice($PDOdb, $object->qty, $object->id, 'propal', $object->fk_propal);

				$pv_calcule = round($n->totalPV / $object->qty, 5); // round car selon les cas, les nombres sont identiques mais sont consiférés comme différents (genr après la virgule il y a un 0000000000000000000000000001 qu'on ne voit pas)
				$pv_manuel = round($object->subprice, 5);
				//var_dump(round($pv_calcule,5) != round($pv_manuel,3),round($pv_calcule,5), round($pv_manuel,5));exit;
				if($pv_calcule != $pv_manuel) $pv_force = true;

			}

			$object->array_options['options_pv_force'] = $pv_force;
			$object->insertExtraFields();
		}
        elseif ($action === 'ORDER_VALIDATE' || $action === 'PROPAL_VALIDATE')
        {
            $PDOdb = new TPDOdb();

            foreach ($object->lines as $line)
            {
                $n = new TNomenclature;
                $n->loadByObjectId($PDOdb, $line->id, $object->element, true, $line->fk_product, $line->qty, $object->id); // si pas de fk_nomenclature, alors on provient d'un document, donc $qty_ref tjr passé en param
//
                if ($n->getId() == 0)
                {
                    $n->fk_object = $line->id;
                    $n->object_type = $object->element;
                    $n->setPrice($PDOdb, $line->qty, $line->id, $object->element, $object->id);
                    $n->save($PDOdb);
                }
            }

        }
		elseif ($action == 'SUPPLIER_PRODUCT_BUYPRICE_UPDATE'){
            $price = $_REQUEST['price'];
            $n = new TNomenclature;
            $n->updateTotalPR($PDOdb, $object, $price, 1);
        }

		if($action == 'PRODUCT_CREATE' && in_array('createfromclone', $object->context) && !empty($conf->global->NOMENCLATURE_CLONE_ON_PRODUCT_CLONE)) {
			$origin_id = (!empty($object->origin_id) && $object->origin == 'product')?$object->origin_id:GETPOST('id', 'int');
			$TNomenclature = TNomenclature::get($PDOdb, $origin_id);
			if(!empty($TNomenclature)) {
				foreach($TNomenclature as $nomenclature) {
					$nomenclature->cloneObject($PDOdb, $object->id);
				}
			}
		}
		return 0;
	}

	private function _setPrice(&$PDOdb, &$object,$fk_parent,$object_type) {
		global $db,$conf,$user,$langs;

		if ($object->product_type > 1 || (empty($conf->global->NOMENCLATURE_USE_SELL_PRICE_INSTEADOF_CALC) && $object->subprice>0)) return 0; //si on ne prends systématique le PV mais que ce dernier est défini, alors il prend le pas. Pour que le prix calculé soit utilisé, il faut un PV = 0

		$n = new TNomenclature;
	    $n->loadByObjectId($PDOdb, $object->id , $object_type, true,$object->fk_product,$object->qty);

		$id = $n->getId();
		if (empty($id) && empty($n->fk_nomenclature_parent)) return 0; // ça veut dire que pas de nomenclature direct ni de nomenclature d'origine

		$n->setPrice($PDOdb, $object->qty, $object->id, $object_type, $fk_parent);


		if (!empty($conf->global->NOMENCLATURE_USE_SELL_PRICE_INSTEADOF_CALC)) {
			$sell_price_to_use=$object->subprice;
		}
		else if (!empty($conf->global->NOMENCLATURE_DONT_USE_NOMENCLATURE_SELL_PRICE)){
		    $sell_price_to_use = 0;
		}
		else {
			$sell_price_to_use=$n->totalPV / $object->qty; // ça doit rester un prix unitaire
		}

		if(empty($sell_price_to_use)) return 0;

		$sell_price_to_use = price2num($sell_price_to_use,'MT'); //round value

		if($object_type=='commande') {
//		var_dump($n->totalPV, $object_type,$object);exit;

			$commande = new Commande($db);
			$commande->fetch($fk_parent);

			$commande->updateline($object->id,$object->desc,$sell_price_to_use,$object->qty,$object->remise_percent,$object->tva_tx,$object->localtax1_tx,$object->localtax2_tx,'HT',0,$object->date_start,$object->date_end,$object->product_type,0,0,$object->fk_fournprice,$n->totalPRCMO / $object->qty,$object->label, $object->special_code, 0, $object->fk_unit, $object->multicurrency_subprice); // Le prix de revient doit aussi rester unitaire

		}

		else if($object_type=='propal') {
			$propal = new Propal($db);
			$propal->fetch($fk_parent);
			$propal->updateline($object->id,$sell_price_to_use,$object->qty,$object->remise_percent,$object->tva_tx,$object->localtax1_tx,$object->localtax2_tx,$object->desc,'HT',0,0,0,0,$object->fk_fournprice, $n->totalPRCMO / $object->qty, $object->label, $object->type, $object->date_start, $object->date_end, 0, $object->fk_unit);


		}else if ($object_type == 'facture') {

			$facture = new Facture($db);
			$facture->fetch($fk_parent);
			$facture->updateline($object->id, $object->desc, $sell_price_to_use, $object->qty, $object->remise_percent, $object->date_start, $object->date_end, $object->tva_tx, $object->localtax1_tx, $object->localtax2_tx, 'HT', 0, $object->product_type, 0, 0, $object->fk_fournprice, $n->totalPRC / $object->qty,$object->label, $object->special_code, 0, $object->situation_percent, $object->fk_unit);
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

                $PDOdb->Execute('DELETE FROM '.MAIN_DB_PREFIX.'nomenclature_workstation WHERE fk_nomenclature = '.$obj->rowid);
                $PDOdb->Execute('DELETE FROM '.MAIN_DB_PREFIX.'nomenclaturedet WHERE fk_nomenclature = '.$obj->rowid);
                $PDOdb->Execute('DELETE FROM '.MAIN_DB_PREFIX.'nomenclature WHERE rowid = '.$obj->rowid);
			}
		}

	}

	private function _duplicateNomenclature(&$PDOdb, $object, $n) {
        $TAttributesToCopy = array('title', 'fk_nomenclature_parent', 'is_default', 'qty_reference', 'note_private', 'non_secable');

        $n_new = new TNomenclature();
        $n_new->loadByObjectId($PDOdb, $object->line->id, $object->element, true, $object->line_from->fk_product, $object->line_from->qty);

        foreach ($TAttributesToCopy as $attribute)
        {
            $n_new->{ $attribute } = $n->{ $attribute };
        }

        if (! empty($n->TNomenclatureDet)) {
            foreach ( $n->TNomenclatureDet as $TDetValues ) {
                $k = $n_new->addChild($PDOdb, 'TNomenclatureDet');
                $n_new->TNomenclatureDet[$k]->set_values($TDetValues);
                $n_new->TNomenclatureDet[$k]->fk_origin = $TDetValues->rowid;
            }
        }
        if (! empty($n->TNomenclatureWorkstation)) {
            foreach ( $n->TNomenclatureWorkstation as $TDetValues ) {

                $k = $n_new->addChild($PDOdb, 'TNomenclatureWorkstation');
                $n_new->TNomenclatureWorkstation[$k]->set_values($TDetValues);
            }
        }

        return $n_new;
    }

    private function _insertNomenclatureAndSetPrice(&$PDOdb, $object) {
		global $conf, $id_origin_line;
        $n = new TNomenclature;

        if(in_array($object->element, array('propal', 'propaldet'))) {
            $element = 'propal';
            $fk_element = 'fk_propal';
        } else if(in_array($object->element, array('commande', 'commandedet'))) {
            $element = 'commande';
            $fk_element = 'fk_commande';
        }

        if(!empty($element)) {
            if(! empty($object->context['subtotalDuplicateLines']))
            {
                $n->loadByObjectId($PDOdb, $object->origin_id, $element, true, 0, $object->qty, $object->{$fk_element});
                // S'il y a bien un load depuis ma ligne de propal d'origine
                if($n->iExist) $n = $this->_duplicateNomenclature($PDOdb, $object, $n);
            }
			elseif(floatval(DOL_VERSION) >= 8.0 && ! empty($object->context)
				&& in_array('createfromclone', $object->context)
				&& !empty($object->origin) && !empty($object->origin_id)
				&& (
					$conf->global->NOMENCLATURE_CLONE_AS_IS_FOR_LINES  // Currently an hidden conf
					|| ( empty($object->fk_product) && in_array($object->fk_product_type, array(0,1)) && !empty($object->NOMENCLATURE_ALLOW_FREELINE) ) // for free lines
				)
			){
				$n->loadByObjectId($PDOdb, $object->origin_id, $element, true, 0, $object->qty, $object->{$fk_element});
				// S'il y a bien un load depuis ma ligne de propal d'origine
				if($n->iExist) $n = $this->_duplicateNomenclature($PDOdb, $object, $n);
			}
            else
			{
				// si pas de fk_nomenclature, alors on provient d'un document, donc $qty_ref tjr passé en param
				$n->loadByObjectId($PDOdb, (!empty($id_origin_line) ? $id_origin_line : $object->id), $element, true, $object->fk_product, $object->qty, $object->{$fk_element});
				$n->reinit();
			}

            if($n->getId() == 0) {
                $n->non_secable = $n->nomenclature_original->non_secable;

                $n->fk_object = $object->id;
                $n->object_type = $element;
                $n->setPrice($PDOdb, $object->qty, $object->id, $element, $object->{$fk_element});
                $n->save($PDOdb);
            }

            $this->_setPrice($PDOdb, $object, $object->{$fk_element}, $element);
        }
    }

}
