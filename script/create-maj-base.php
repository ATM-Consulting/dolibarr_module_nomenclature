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
/*
 * Fin récup
 */
 
 
 
$o=new TNomenclatureCoefObject($db);
$o->init_db_by_vars($PDOdb);
