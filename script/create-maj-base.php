<?php
/*
 * Script créant et vérifiant que les champs requis s'ajoutent bien
 */

if(!defined('INC_FROM_DOLIBARR')) {
	define('INC_FROM_CRON_SCRIPT', true);

	require('../config.php');

}

global $db;

dol_include_once('/nomenclature/class/nomenclature.class.php');
require_once DOL_DOCUMENT_ROOT . '/core/class/extrafields.class.php';

if (isset($conf->global->NOMENCLATURE_COEF_FOURNITURE) || isset($conf->global->NOMENCLATURE_COEF_CONSOMMABLE))
{
	$sql = 'ALTER TABLE '.MAIN_DB_PREFIX.'nomenclaturedet CHANGE product_type code_type VARCHAR(30)';
	$db->query($sql);
}

$PDOdb=new TPDOdb;

$o=new TNomenclature($db);
$o->init_db_by_vars($PDOdb);

$o=new TNomenclatureDet($db);
$o->init_db_by_vars($PDOdb);


$o=new TNomenclatureWorkstation($db);
$o->init_db_by_vars($PDOdb);

$o=new TNomenclatureCoef($db);
$o->init_db_by_vars($PDOdb);

/*
 * Récupération des anciennes valeurs pour les utiliser avec le nouveau système
 */
if (isset($conf->global->NOMENCLATURE_COEF_FOURNITURE))
{
	$o=new TNomenclatureCoef;
	$o->label = 'Fourniture';
	$o->description = "Coef. de frais généraux (stockage, appro, ...) sur Fourniture";
	$o->code_type = "coef_fourniture";
	$o->tx = $conf->global->NOMENCLATURE_COEF_FOURNITURE;
	$o->save($PDOdb);

	dolibarr_del_const($db, 'NOMENCLATURE_COEF_FOURNITURE', $conf->entity);

	$sql = 'UPDATE '.MAIN_DB_PREFIX.'nomenclaturedet SET code_type = "coef_fourniture" WHERE code_type IN ("1", "2")';
	$db->query($sql);
}

if (isset($conf->global->NOMENCLATURE_COEF_CONSOMMABLE))
{
	$o=new TNomenclatureCoef;
	$o->label = 'Consommable';
	$o->description = "Coef. de frais généraux (stockage, appro, ...) sur consommable";
	$o->code_type = "coef_consommable";
	$o->tx = $conf->global->NOMENCLATURE_COEF_CONSOMMABLE;
	$o->save($PDOdb);

	dolibarr_del_const($db, 'NOMENCLATURE_COEF_CONSOMMABLE', $conf->entity);

	$sql = 'UPDATE '.MAIN_DB_PREFIX.'nomenclaturedet SET code_type = "coef_consommable" WHERE code_type = "3"';
	$db->query($sql);
}

if (isset($conf->global->NOMENCLATURE_COEF_MARGE))
{
	$o=new TNomenclatureCoef;
	$o->label = 'Marge';
	$o->description = "Coef. de marge";
	$o->code_type = "coef_final";
	$o->tx = $conf->global->NOMENCLATURE_COEF_MARGE;
	$o->save($PDOdb);

	dolibarr_del_const($db, 'NOMENCLATURE_COEF_MARGE', $conf->entity);
}
else
{
	$o=new TNomenclatureCoef($db);
	$o->loadBy($PDOdb, 'coef_final', 'code_type');

	if ($o->getId() > 0) null; //OK le coef exist donc on ne fait rien
	else
	{
        //Il faut créer le coef_final car il s'agit d'un coef obligatoire pour des calculs

        $o->loadBy($PDOdb, 'coef_marge', 'code_type'); //coef_marge existe mais pas le coef_final --> on prend la valeur du coef_marge

        if($o->getId() > 0){

            $tx_coeffinal = $o->tx;

            $o=new TNomenclatureCoef;
            $o->label = 'Marge Finale';
            $o->description = "Coef. du prix de vente conseillé";
            $o->code_type = "coef_final";
            $o->type = "pricefinal";
            $o->tx = $tx_coeffinal;
            $o->entity = $conf->entity;
            $o->save($PDOdb);

        } else
        {
            $o = new TNomenclatureCoef;
            $o->label = 'Marge Finale';
            $o->description = "Coef. du prix de vente conseillé";
            $o->code_type = "coef_final";
            $o->type = "pricefinal";
            $o->tx = 1.1;
            $o->entity = $conf->entity;
            $o->save($PDOdb);
        }
	}
}

/*
 * Fin récup
 */


$o=new TNomenclatureCoefObject($db);
$o->init_db_by_vars($PDOdb);


$o=new TNomenclatureWorkstationThmObject;
$o->init_db_by_vars($PDOdb);


$o=new TNomenclatureFeedback;
$o->init_db_by_vars($PDOdb);



// MAJ Champ type de la table llx_nomenclature_coef pour utiliser par défaut la valeur "nomenclature"
$db->query('UPDATE '.MAIN_DB_PREFIX.'nomenclature_coef SET type = "nomenclature" WHERE type IS NULL');
$db->query('UPDATE '.MAIN_DB_PREFIX.'nomenclature_coef_object SET type = "nomenclature" WHERE type IS NULL');

// Gestion des PV forcés
$e = new ExtraFields($db);
$e->addExtraField('pv_force', 'Prix de vente forcé', 'boolean', '', '', 'propaldet', 0, 0, '', '', 0, '', 0, 1);