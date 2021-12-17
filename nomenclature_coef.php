<?php

require 'config.php';
dol_include_once('/nomenclature/class/nomenclature.class.php');
dol_include_once('/nomenclature/lib/nomenclature.lib.php');

$hookmanager->initHooks(array('nomenclature_coef'));

$PDOdb = new TPDOdb;
$fiche = GETPOST('fiche', 'alpha');
$action = GETPOST('action', 'alpha');

$object = '';
$parameters = array('fiche' => $fiche);
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action); // Note that $action and $object may have been modified by some hooks
if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');

if(GETPOST('deleteSpecific', 'none')) {
	if (!empty($conf->global->NOMENCLATURE_USE_CUSTOM_THM_FOR_WS) && $fiche == 'propal')
	{
		require_once DOL_DOCUMENT_ROOT.'/comm/propal/class/propal.class.php';
		$object = new Propal($db);
		$object->fetch((int)GETPOST('id', 'int'));
		TNomenclatureWorkstationThmObject::deleteAllThmObject($PDOdb, $object->id, $object->element);
	}

	TNomenclatureCoefObject::deleteCoefsObject($PDOdb, (int)GETPOST('id', 'int'), $fiche);

	// Si je supprime les coef custom, alors je dois ré-appliquer en automatique les prix de vente (sinon ça veut dire qu'on laisse la possibilité de supprimer les coef et de garder des montants lignes incohérents)
	if ($fiche == 'propal') _updateLinePriceObject($PDOdb, $db, $conf, $langs, $user, 'propal');

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

		_fiche_tiers($PDOdb, $db, $conf, $langs, $user, $action);
		break;
	case 'propal':
		dol_include_once('/comm/propal/class/propal.class.php');
		dol_include_once('/core/lib/propal.lib.php');

		if ($action == 'updatecoef')
		{
			if (!empty($conf->global->NOMENCLATURE_USE_CUSTOM_THM_FOR_WS))
			{
				$object = new Propal($db);
				$object->fetch((int)GETPOST('id', 'int'));

				$TNomenclatureWorkstationThmObject = GETPOST('TNomenclatureWorkstationThmObject', 'none');
				TNomenclatureWorkstationThmObject::updateAllThmObject($PDOdb, $object, $TNomenclatureWorkstationThmObject);
			}

			_updateCoef($PDOdb, $db, $conf, $langs, $user);

			if (GETPOST('update_line_price', 'none')) _updateLinePriceObject($PDOdb, $db, $conf, $langs, $user, 'propal');

			header('Location: '.dol_buildpath('/nomenclature/nomenclature_coef.php?id='.GETPOST('id', 'int').'&fiche=propal', 1));
			exit;
		}

		_fiche_propal($PDOdb, $db, $conf, $langs, $user, $action);
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

		_fiche_tiers($PDOdb, $db, $conf, $langs, $user, $action);
		break;
}

function _fiche_tiers(&$PDOdb, &$db, &$conf, &$langs, &$user, $action='')
{
	global $object;
	$id = GETPOST('socid', 'int');
	$object = new Societe($db);
	$object->fetch($id);

	llxHeader('','Coefficients');

	$head = societe_prepare_head($object, $user);
	$titre = $langs->trans('ThirdParty');
	$picto = 'company';
	dol_fiche_head($head, 'nomenclaturecoef', $titre, 0, $picto);
	$TCoefObject = TNomenclatureCoefObject::loadCoefObject($PDOdb, $object, 'tiers');

	_print_list_coef($PDOdb, $db, $langs, $object, $TCoefObject, $langs->trans("ThirdPartyName"), 'socid', 'nom', 'tiers', $id, $action);
}

function _fiche_propal(&$PDOdb, &$db, &$conf, &$langs, &$user, $action='')
{
	global $object;
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

	_print_list_coef($PDOdb, $db, $langs, $object, $TCoefObject, $langs->trans("Ref"), 'id', 'ref', 'propal', $id, $action);
}

function _print_list_coef(&$PDOdb, &$db, &$langs, &$object, &$TCoefObject, $label, $paramid, $fieldname, $fiche, $id, $action='')
{
	global $hookmanager,$conf;

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
	$background_title = '#f2f2f2';
	$background_line = '#eeeeff';

	echo '<tr style="background:'.$background_title.';"><td colspan="3"><b>' . $langs->trans("CoefList") . '</b></td></tr>';
	_printCoef($object, $TCoefObject, 'nomenclature', $background_line);

	if(!empty($conf->workstationatm->enabled))
	{
		echo '<tr style="background:'.$background_title.';"><td colspan="3"><b>' . $langs->trans("CoefListWorkstation") . '</b></td></tr>';
		_printCoef($object, $TCoefObject, 'workstation', $background_line);
	}

	if ($fiche == 'propal' && !empty($conf->global->NOMENCLATURE_USE_CUSTOM_THM_FOR_WS))
	{
		$TNomenclatureWorkstationThmObject = TNomenclatureWorkstationThmObject::loadAllThmObject($PDOdb, $object, $object->element);
		if (!empty($TNomenclatureWorkstationThmObject))
		{
			echo '<tr style="background:'.$background_title.';">';
			echo '<td colspan="3"><b>'.$langs->trans('workstationListCustomTHM').'</b></td>';
			echo '</tr>';

			foreach ($TNomenclatureWorkstationThmObject as $thm_object)
			{
				$name ='TNomenclatureWorkstationThmObject['.$thm_object->fk_workstation.']';

				echo '<tr style="background:'.( $thm_object->getId()>0 ? 'white' : $background_line  ).'">';
				echo '<td>&nbsp;'.$thm_object->label.( $thm_object->getId()>0 ? '' : img_help(1,$langs->trans('ThmGenericSaveForSpecific') ) ).'</td>';
				echo '<td>'.$thm_object->description.'</td>';
				echo '<td>';
				// Si on est sur une propal et que son statut est > à brouillon alors on affiche juste la valeur
				if ($object->element == 'propal' && $object->statut > 0)
				{
					echo $thm_object->thm_object;
				}
				else
				{
					echo '<input name="'.$name.'" value="'.$thm_object->thm_object.'" size="5" />';
				}
				echo '</td>';
				echo '</tr>';
			}
		}
	}

	// Other attributes
	$parameters = array('paramid'=>$paramid, 'fiche'=>$fiche, 'id'=>$id, 'background_title'=>$background_title, 'background_line'=>$background_line);
	$reshook = $hookmanager->executeHooks('formAddObjectLine', $parameters, $object, $action); // Note that $action and $object may have been modified by hook

    echo "</table>\n";


	echo '<div class="tabsAction">';

	if ($object->statut == 0)
	{
		// l'action par défaut = updatecoef (donc sur une propal il est préférable de ne pas laisser la possibilité au client d'enregistrer des coefs custom sans les appliquer)
		if ($fiche == 'propal') echo '<div class="inline-block divButAction"><input class="butAction" type="submit" name="update_line_price" value="'.$langs->trans('ApplyNewCoefToObjectLine').'" /></div>';
		else echo '<div class="inline-block divButAction"><input class="butAction" type="submit" name="save" value="'.$langs->trans('Save').'" /></div>';

        $foundCoefObject = false;
        foreach ($TCoefObject as $obj)
        {
            if (get_class($obj) == 'TNomenclatureCoefObject')
            {
                $foundCoefObject = true;
                break;
            }
        }
        if($foundCoefObject) {
			echo '<div class="inline-block divButAction"><input class="butActionDelete" type="submit" name="deleteSpecific" value="'.$langs->trans('DeleteSpecificCoef').'" /></div>';
		}
	}

	$parameters = array('paramid'=>$paramid, 'fiche'=>$fiche, 'id'=>$id);
	$reshook = $hookmanager->executeHooks('addMoreActionsButtons', $parameters, $object, $action); // Note that $action and $object may have been modified by hook

	echo '</div>';


    echo '</form>';

	llxFooter();
}

function _printCoef(&$object, &$TCoefObject, $type_coef, $background_line)
{
	global $langs;

	if (!empty($TCoefObject))
	{
		foreach ($TCoefObject as $type=>&$coef)
		{
			if ($coef->type != $type_coef) continue;

			$basename ='TNomenclatureCoefObject['.$type.']';

			echo '<tr style="background:'.( $coef->rowid>0 ? 'white' : $background_line  ).'">';
			echo '<td>&nbsp;'.$coef->label.( $coef->rowid>0 ? '' : img_help(1,$langs->trans('CoefGenericSaveForSpecific') ) ).'</td>';
			echo '<td>'.$coef->description.'</td>';
			echo '<td>';

			// Si on est sur une propal et que son statut est > à brouillon alors on affiche juste la valeur
			if ($object->element == 'propal' && $object->statut > 0)
			{
				echo $coef->tx_object;
			}
			else
			{
				print '<input type="text" name="'.$basename.'[tx_object]" value="'.$coef->tx_object.'" size="5" />';
				print '<input type="hidden" name="'.$basename.'[type]" value="'.$coef->type.'" />';
			}
			echo '</td>';
			echo '</tr>';
		}
	}
}

function _updateCoef(&$PDOdb, &$db, &$conf, &$langs, &$user)
{
	dol_include_once('/core/lib/functions.lib.php');

	$fk_object = GETPOST('id', 'int');
	$type_object = GETPOST('fiche', 'alpha');

	$TNomenclatureCoefObject = GETPOST('TNomenclatureCoefObject', 'none');
//	var_dump($TNomenclatureCoefObject);exit;
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
//	dol_include_once('/nomenclature/nomenclature.php');
	global $conf;

	$id = GETPOST('id', 'int');

	switch ($object_type) {
		case 'propal':
			require_once DOL_DOCUMENT_ROOT . '/comm/propal/class/propal.class.php';
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

	// Chargement des coefs fraichement enregistrés
    $TCoef = TNomenclatureCoefObject::loadCoefObject($PDOdb, $object, $object->element);
	foreach ($object->lines as $line)
	{
		if ($line->product_type == 9 || (!empty($conf->global->NOMENCLATURE_DONT_RECALCUL_IF_PV_FORCE) && !empty($line->array_options['options_pv_force']))) continue;

		$nomenclature = new TNomenclature;
		$nomenclature->loadByObjectId($PDOdb, $line->id, 'propal', true, $line->fk_product, $line->qty);

        foreach ($nomenclature->TNomenclatureDet as &$det)
        {
            if (isset($TCoef[$det->code_type]))
            {
                $det->tx_custom = $TCoef[$det->code_type]->tx;
            }
			if (isset($TCoef[$det->code_type2]))
			{
				$det->tx_custom2 = $TCoef[$det->code_type2]->tx;
			}
			$det->save($PDOdb);
        }


		$nomenclature->setPrice($PDOdb,$line->qty,$line->id,'propal',$object->id);

		_updateObjectLine($nomenclature, $object_type, $line->id, $object->id, true);

	}

}
