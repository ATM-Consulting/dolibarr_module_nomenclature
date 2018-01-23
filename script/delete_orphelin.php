<?php

require '../config.php';
dol_include_once('/nomenclature/class/nomenclature.class.php');

$PDOdb = new TPDOdb;

echo '<b>------ START ------</b><br /><br />';

// Recherche des nomenclatures liées aux lignes de propal qui n'existent plus
$sql = '
	SELECT rowid FROM '.MAIN_DB_PREFIX.'nomenclature
	WHERE object_type = \'propal\'
	AND fk_object NOT IN (SELECT rowid FROM '.MAIN_DB_PREFIX.'propaldet)
';

echo "<u>Recherche des nomenclatures liées aux lignes de propal qui n'existent plus</u><br />";
echo '<pre>'.$sql.'</pre>';

$resql = $db->query($sql);
if ($resql)
{
	while ($obj = $db->fetch_object($resql))
	{
		echo 'LOAD '.$obj->rowid;
		$n = new TNomenclature;
		$n->load($PDOdb, $obj->rowid, true);
		
		$n->delete($PDOdb);
		echo ' => deleted<br />';
	}
	
	if ($db->num_rows($resql) == 0)
	{
		echo 'RAS<br />';
	}
}


// Recherche des nomenclatures liées aux produits qui n'existent plus
$sql = '
	SELECT rowid FROM '.MAIN_DB_PREFIX.'nomenclature
	WHERE object_type = \'product\'
	AND fk_object NOT IN (SELECT rowid FROM '.MAIN_DB_PREFIX.'product)
';

echo "<br /><u>Recherche des nomenclatures liées aux produits qui n'existent plus</u><br />";
echo '<pre>'.$sql.'</pre>';

$resql = $db->query($sql);
if ($resql)
{
	while ($obj = $db->fetch_object($resql))
	{
		echo 'LOAD '.$obj->rowid;
		$n = new TNomenclature;
		$n->load($PDOdb, $obj->rowid, true);
		
		$n->delete($PDOdb);
		echo ' => deleted<br />';
	}
	
	if ($db->num_rows($resql) == 0)
	{
		echo 'RAS<br />';
	}
}

echo '<br /><b>------ END ------</b>';