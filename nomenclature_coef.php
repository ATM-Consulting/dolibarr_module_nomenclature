<?php

require 'config.php';
dol_include_once('/nomenclature/class/nomenclature.class.php');

$PDOdb = new TPDOdb;
$fiche = GETPOST('fiche', 'alpha');
$action = GETPOST('action', 'alpha');

switch ($fiche) {
	case 'tiers':
		dol_include_once('/societe/class/societe.class.php');
		dol_include_once('/core/lib/company.lib.php');
		
		if ($action == 'updatecoef')
		{
			_updateCoef($PDOdb, $db, $conf, $langs, $user);
			header('Location: '.dol_buildpath('/nomenclature/nomenclature_coef.php?socid='.GETPOST('id', 'int').'&fiche=tiers', 2));
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
			header('Location: '.dol_buildpath('/nomenclature/nomenclature_coef.php?id='.GETPOST('id', 'int').'&fiche=propal', 2));
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
			header('Location: '.dol_buildpath('/nomenclature/nomenclature_coef.php?socid='.GETPOST('id', 'int').'&fiche=tiers', 2));
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

	llxHeader('','Coefficient');

    $head = societe_prepare_head($object, $user);
	$titre = $langs->trans('ThirdParty');
	$picto = 'company';
	dol_fiche_head($head, 'nomenclaturecoef', $titre, 0, $picto);
	
	$TCoef = TNomenclatureCoef::loadCoef($PDOdb);
	$TCoefObject = TNomenclatureCoefObject::loadCoefObject($PDOdb, $object, 'tiers');
	
	_print_list_coef($PDOdb, $db, $langs, $object, $TCoef, $TCoefObject, $langs->trans("ThirdPartyName"), 'socid', 'nom', 'tiers', $id);
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
	
	$TCoef = TNomenclatureCoef::loadCoef($PDOdb);
	$TCoefObject = TNomenclatureCoefObject::loadCoefObject($PDOdb, $object, 'propal');
	//var_dump($TCoefObject);
	_print_list_coef($PDOdb, $db, $langs, $object, $TCoef, $TCoefObject, $langs->trans("Ref"), 'id', 'ref', 'propal', $id);
}

function _print_list_coef(&$PDOdb, &$db, &$langs, &$object, &$TCoef, &$TCoefObject, $label, $paramid, $fieldname, $fiche, $id)
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
    echo $form->showrefnav($object, $paramid, '', 1, 'rowid', $fieldname);
    echo '</td>';
    echo '</tr>';
    
    // List of coef
    echo '<tr style="background:#f2f2f2;"><td colspan="3"><b>' . $langs->trans("CoefList") . '</b></td></tr>';
    
	if ($TCoef)
	{
		foreach ($TCoef as $coef)
		{
			$name = !empty($TCoefObject[$coef->code_type]) && !$TCoefObject[$coef->code_type]->fk_origin ? 'TNomenclatureCoefObject_update['.$TCoefObject[$coef->code_type]->rowid.']' : 'TNomenclatureCoefObject_create['.$coef->code_type.']';
			echo '<tr>';
			echo '<td>&nbsp;'.$coef->label.'</td>';
			echo '<td>'.$coef->description.'</td>';
			echo '<td><input name="'.$name.'" value="'.(!empty($TCoefObject[$coef->code_type]) ? $TCoefObject[$coef->code_type]->tx_object : $coef->tx).'" size="5" /></td>';
			echo '</tr>';
		}
	}
	
	
    echo "</table>\n";
	
	echo '<div class="tabsAction"><div class="inline-block divButAction"><input class="butAction" type="submit" name="save" value="'.$langs->trans('Save').'" /></div></div>';
	
	
    echo '</form>';
  	echo '<br />';
	
	llxFooter();
}

function _updateCoef(&$PDOdb, &$db, &$conf, &$langs, &$user)
{
	dol_include_once('/core/lib/functions.lib.php');
	
	$fk_object = GETPOST('id', 'int');
	$type_object = GETPOST('fiche', 'alpha');
	
	$Post_TNomenclatureCoefObject_create = GETPOST('TNomenclatureCoefObject_create');
	$Post_TNomenclatureCoefObject_update = GETPOST('TNomenclatureCoefObject_update');
	
	if ($Post_TNomenclatureCoefObject_create) 
	{
		foreach ($Post_TNomenclatureCoefObject_create as $code_type => $tx_value)
		{
			$obj = new TNomenclatureCoefObject;
			$obj->fk_object = $fk_object;
			$obj->type_object = $type_object;
			$obj->code_type = $code_type;
			$obj->tx_object = (float) price2num($tx_value);
			$obj->save($PDOdb);
		}
	}
	
	if ($Post_TNomenclatureCoefObject_update)
	{
		foreach ($Post_TNomenclatureCoefObject_update as $id_coef_object => $tx_value)
		{
			$obj = new TNomenclatureCoefObject;
			if ($obj->load($PDOdb, $id_coef_object))
			{
				$obj->tx_object = (float) price2num($tx_value);
				$obj->save($PDOdb);
			}
			
		}	
	}
	
	setEventMessages($langs->trans('nomenclatureCoefUpdated'), null);
}
