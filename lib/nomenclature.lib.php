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
 *	\file		lib/nomenclature.lib.php
 *	\ingroup	nomenclature
 *	\brief		This file is an example module library
 *				Put some comments here
 */

function nomenclatureAdminPrepareHead()
{
    global $langs, $conf;

    $langs->load("nomenclature@nomenclature");

    $h = 0;
    $head = array();

    $head[$h][0] = dol_buildpath("/nomenclature/admin/nomenclature_setup.php", 1);
    $head[$h][1] = $langs->trans("Parameters");
    $head[$h][2] = 'settings';
    $h++;
	
    $head[$h][0] = dol_buildpath("/nomenclature/admin/import.php", 1);
    $head[$h][1] = $langs->trans("Import");
    $head[$h][2] = 'import';
    $h++;
    $head[$h][0] = dol_buildpath("/nomenclature/admin/nomenclature_about.php", 1);
    $head[$h][1] = $langs->trans("About");
    $head[$h][2] = 'about';
    $h++;

    // Show more tabs from modules
    // Entries must be declared in modules descriptor with line
    //$this->tabs = array(
    //	'entity:+tabname:Title:@nomenclature:/nomenclature/mypage.php?id=__ID__'
    //); // to add new tab
    //$this->tabs = array(
    //	'entity:-tabname:Title:@nomenclature:/nomenclature/mypage.php?id=__ID__'
    //); // to remove a tab
    complete_head_from_modules($conf, $langs, $object, $head, $h, 'nomenclature');

    return $head;
}


function cloneNomenclatureFromProduct(&$PDOdb, $fk_product, $fk_object, $object_type, $json = false)
{
	$TNomen = TNomenclature::get($PDOdb, $fk_product,false, 'product');
    foreach($TNomen as &$n) {
        
        $n->reinit();
        $n->fk_object = $fk_object;
        $n->object_type = $object_type;
        $n->save($PDOdb);
    }
    
    if (!$json) setEventMessage('NomenclatureCloned');
}

function _updateObjectLine(&$n, $object_type, $fk_object, $fk_origin, $apply_nomenclature_price=false)
{
	global $db;

	if (! empty($apply_nomenclature_price))
	{
		switch ($object_type) {
			case 'propal':
				dol_include_once('/comm/propal/class/propal.class.php');
				
				$propal = new Propal($db);
				$propal->fetch($fk_origin);
				
				foreach ($propal->lines as $line)
				{
					if ($line->id == $fk_object)
					{
						$propal->updateline($fk_object, $n->getSellPrice($line->qty), $line->qty, $line->remise_percent, $line->tva_tx, $line->localtax1_tx, $line->localtax2_tx, $line->desc, 'HT', $line->info_bits, $line->special_code, $line->fk_parent_line, $line->skip_update_total, $line->fk_fournprice, $n->getBuyPrice($line->qty), $line->product_label, $line->product_type, $line->date_start, $line->date_end, $line->array_options, $line->fk_unit);
						$line->array_options['options_pv_force'] = false;
						$line->insertExtraFields();
					}
				}

				break;
			case 'commande':
				dol_include_once('/commande/class/commande.class.php');

				$commande = new Commande($db);
				$commande->fetch(GETPOST('fk_origin', 'int'));

				foreach ($commande->lines as $line)
				{
					if ($line->id == $fk_object)
					{
						$commande->updateline($fk_object, $line->desc, $n->getSellPrice($line->qty), $line->qty, $line->remise_percent, $line->tva_tx, $line->localtax1_tx, $line->localtax2_tx, 'HT', $line->info_bits, $line->date_start, $line->date_end, $line->product_type, $line->fk_parent_line, $line->skip_update_total, $line->fk_fournprice, $n->getBuyPrice($line->qty), $line->product_label, $line->special_code, $line->array_options, $line->fk_unit);
					}
				}
				break;
		}

	}
}


function getFormConfirmNomenclature(&$form, &$product, $fk_nomenclature_used, $action, $qty_reference=1)
{
    global $langs,$db;
    
	$formconfirm = '';
	if ($action == 'create_stock')
    {
		require_once DOL_DOCUMENT_ROOT.'/product/class/html.formproduct.class.php';
		
		$qty = GETPOST('nomenclature_qty_to_create', 'int');
		if (empty($qty)) $qty = $qty_reference;
		
		$formproduct = new FormProduct($db);
		$formproduct->loadWarehouses($product->id, '', '', true);
		
        $text = '';
		$formquestion = array(
			array('type' => 'hidden'	,'name' => 'fk_product'					,'value' => $product->id)
			,array('type' => 'hidden'	,'name' => 'fk_nomenclature_used'		,'value' => $fk_nomenclature_used)
			,array('type' => 'text'		,'name' => 'nomenclature_qty_to_create'	,'label' => $langs->trans('NomenclatureHowManyQty')				,'value' => $qty, 'moreattr' => 'size="5"')
			,array('type' => 'other'	,'name' => 'fk_warehouse_to_make'		,'label' => $langs->trans('NomenclatureSelectWarehouseToMake')	,'value' => $formproduct->selectWarehouses(GETPOST('fk_warehouse_to_make'), 'fk_warehouse_to_make', 'warehouseopen,warehouseinternal', 0, 0, 0, '', 0, 0, null, 'minwidth200'))
			,array('type' => 'other'	,'name' => 'fk_warehouse_needed'		,'label' => $langs->trans('NomenclatureSelectWarehouseNeeded')	,'value' => $formproduct->selectWarehouses(GETPOST('fk_warehouse_needed'), 'fk_warehouse_needed', 'warehouseopen,warehouseinternal', 0, 0, 0, '', 0, 0, null, 'minwidth200'))
		);
		
        $formconfirm = $form->formconfirm($_SERVER['PHP_SELF'] . '?fk_product=' . $product->id, $langs->trans('NomenclatureCreateStock', $product->ref), $text, 'confirm_create_stock', $formquestion, 0, 1, 'auto');
    }
    
    return $formconfirm;
}