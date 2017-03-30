<?php

require 'config.php';
dol_include_once('/nomenclature/class/nomenclature.class.php');

$PDOdb = new TPDOdb;
$fiche = GETPOST('fiche', 'alpha');
$action = GETPOST('action', 'alpha');

if(GETPOST('deleteSpecific')) {
	
	TNomenclatureCoefObject::deleteCoefsObject($PDOdb, GETPOST('id'), $fiche);
	header('Location: '.dol_buildpath('/nomenclature/nomenclature_coef.php?socid='.GETPOST('id', 'int').'&id='.GETPOST('id', 'int').'&fiche='.$fiche, 1));
	exit;
	
}

switch ($fiche) {
	case 'tiers':
		dol_include_once('/societe/class/societe.class.php');
		dol_include_once('/core/lib/company.lib.php');
		
		if ($action == 'updatecoef')
		{
			$res = _updateCoef($PDOdb, $db, $conf, $langs, $user);
			header('Location: '.dol_buildpath('/nomenclature/nomenclature_coef.php?socid='.GETPOST('id', 'int').'&fiche=tiers', 1));
			exit;
		}
		
		_fiche_tiers($PDOdb, $db, $conf, $langs, $user);
		break;
	case 'propal':
		dol_include_once('/comm/propal/class/propal.class.php');
		dol_include_once('/core/lib/propal.lib.php');
		
		if ($action == 'updatecoef')
		{
			_updateCoef($PDOdb, $db, $conf, $langs, $user);
			
			if (GETPOST('update_line_price')) _updateLinePriceObject($PDOdb, $db, $conf, $langs, $user, 'propal');
			
			header('Location: '.dol_buildpath('/nomenclature/nomenclature_coef.php?id='.GETPOST('id', 'int').'&fiche=propal', 1));
			exit;
		}
		
		_fiche_propal($PDOdb, $db, $conf, $langs, $user);
		break;
	
	default:
		dol_include_once('/societe/class/societe.class.php');
		dol_include_once('/core/lib/company.lib.php');
		
		if ($action == 'updatecoef')
		{
			_updateCoef($PDOdb, $db, $conf, $langs, $user);
			header('Location: '.dol_buildpath('/nomenclature/nomenclature_coef.php?socid='.GETPOST('id', 'int').'&fiche=tiers', 1));
			exit;
		}
		
		_fiche_tiers($PDOdb, $db, $conf, $langs, $user);
		break;
}

function _fiche_tiers(&$PDOdb, &$db, &$conf, &$langs, &$user)
{
	$id = GETPOST('socid', 'int');
	$object = new Societe($db);
	$object->fetch($id);

	llxHeader('','Coefficients');

	$head = societe_prepare_head($object, $user);
	$titre = $langs->trans('ThirdParty');
	$picto = 'company';
	dol_fiche_head($head, 'nomenclaturecoef', $titre, 0, $picto);
	$TCoefObject = TNomenclatureCoefObject::loadCoefObject($PDOdb, $object, 'tiers');
	
	_print_list_coef($PDOdb, $db, $langs, $object, $TCoefObject, $langs->trans("ThirdPartyName"), 'socid', 'nom', 'tiers', $id);
}

function _fiche_propal(&$PDOdb, &$db, &$conf, &$langs, &$user)
{
	$id = GETPOST('id', 'int');
	$object = new Propal($db);
	$object->fetch($id);
	$object->fetch_thirdparty();
	
	llxHeader('','Coefficient');

    $head = propal_prepare_head($object, $user);
	$titre = $langs->trans('Proposal');
	$picto = 'propal';
	dol_fiche_head($head, 'nomenclaturecoef', $titre, 0, $picto);
	
	$TCoefObject = TNomenclatureCoefObject::loadCoefObject($PDOdb, $object, 'propal');
	
	_print_list_coef($PDOdb, $db, $langs, $object, $TCoefObject, $langs->trans("Ref"), 'id', 'ref', 'propal', $id);
}

function _print_list_coef(&$PDOdb, &$db, &$langs, &$object, &$TCoefObject, $label, $paramid, $fieldname, $fiche, $id)
{
	$form = new Form($db);
    echo '<form action="'.$_SERVER['PHP_SELF'].'" method="POST">';
	echo '<input type="hidden" name="id" value="'.$id.'" />';
	echo '<input type="hidden" name="fiche" value="'.$fiche.'" />';
	echo '<input type="hidden" name="action" value="updatecoef" />';
    echo '<table class="border" width="100%">';
    
    // Name
    echo '<tr>';
    echo '<td width="15%">' . $label . '</td><td colspan="2">';
    echo $form->showrefnav($object, $paramid, '', 0, 'rowid', $fieldname);
    echo '</td>';
    echo '</tr>';
    
    // List of coef
    echo '<tr style="background:#f2f2f2;"><td colspan="3"><b>' . $langs->trans("CoefList") . '</b></td></tr>';
    
	if (!empty($TCoefObject))
	{
		foreach ($TCoefObject as $type=>&$coef)
		{
			$name ='TNomenclatureCoefObject['.$type.'][tx_object]';
			
			echo '<tr style="background:'.( $coef->rowid>0 ? 'white' : '#eeeeff'  ).'">';
			echo '<td>&nbsp;'.$coef->label.( $coef->rowid>0 ? '' : img_help(1,$langs->trans('CoefGenericSaveForSpecific') ) ).'</td>';
			echo '<td>'.$coef->description.'</td>';
			echo '<td><input name="'.$name.'" value="'.$coef->tx_object.'" size="5" /></td>';
			echo '</tr>';
		}
	}
	
	
    echo "</table>\n";
	
	echo '<div class="tabsAction">';
	
	if($coef->rowid>0) {
		echo '<div class="inline-block divButAction"><input class="butAction" type="submit" name="deleteSpecific" value="'.$langs->trans('DeleteSpecificCoef').'" /></div>';
	}
	
	echo '<div class="inline-block divButAction"><input class="butAction" type="submit" name="save" value="'.$langs->trans('Save').'" /></div>';

	if ($fiche == 'propal') echo '<br /><div class="inline-block divButAction"><input class="butAction" type="submit" name="update_line_price" value="'.$langs->trans('ApplyNewCoefToObjectLine').'" /></div>';
				
	echo '</div>';
	
	
    echo '</form>';
	
	llxFooter();
}

function _updateCoef(&$PDOdb, &$db, &$conf, &$langs, &$user)
{
	dol_include_once('/core/lib/functions.lib.php');
	
	$fk_object = GETPOST('id', 'int');
	$type_object = GETPOST('fiche', 'alpha');
	
	$TNomenclatureCoefObject = GETPOST('TNomenclatureCoefObject');
//	var_dump($TNomenclatureCoefObject);
	if (!empty($TNomenclatureCoefObject)) 
	{
		foreach ($TNomenclatureCoefObject as $code_type => &$coef)
		{
			$obj = new TNomenclatureCoefObject;
			$obj->loadByTypeByCoef($PDOdb, $code_type, $fk_object, $type_object);
			$obj->set_values($coef);
			
			
			$obj->fk_object = $fk_object;
			$obj->type_object = $type_object;
			$obj->code_type = $code_type;
//	$PDOdb->debug = 1;		
			
			$obj->save($PDOdb);
		}
	}
//		exit;
	setEventMessages($langs->trans('nomenclatureCoefUpdated'), null);
}

function _updateLinePriceObject(&$PDOdb, &$db, &$conf, &$langs, &$user, $object_type)
{
	$id = GETPOST('id', 'int');
	
	switch ($object_type) {
		case 'propal':
			$object = new Propal($db);
			$object->fetch($id);
			$object->fetch_thirdparty();
			
			if ($object->statut != Propal::STATUS_DRAFT)
			{
				setEventMessages($langs->trans('nomenclatureApplyAllCoefOnPriceError'), null, 'errors');
				return;
			}
			break;
		
		default:
			return false;
			break;
	}
	
	foreach ($object->lines as $line)
	{
		if ($line->product_type == 9) continue;
		
		$nomenclature = new TNomenclature;
		$nomenclature->loadByObjectId($PDOdb, $line->id, 'propal', true, $line->fk_product, $line->qty);
		$nomenclature->setPrice($PDOdb,$line->qty,$line->id,'propal',$object->id);

		$price_buy = $nomenclature->totalMO+$nomenclature->totalPRC;
		$price_to_sell = $nomenclature->totalPV / $line->qty;
		
		//Puis mettre à jour son prix
		if ($object->element == 'propal')$object->updateline($line->id, $price_to_sell, $line->qty, $line->remise_percent, $line->tva_tx, $line->localtax1_tx, $line->localtax2_tx, $line->desc, 'HT', $line->info_bits, $line->special_code, $line->fk_parent_line, $line->skip_update_total, $line->fk_fournprice, $price_buy, $line->product_label, $line->product_type, $line->date_start, $line->date_end, $line->array_options, $line->fk_unit);
		
	}
	
}
