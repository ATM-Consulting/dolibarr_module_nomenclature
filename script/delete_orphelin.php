<?php
/* Copyright (C) 2025 ATM Consulting
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

require '../config.php';
dol_include_once('/nomenclature/class/nomenclature.class.php');
global $db;
$PDOdb = new TPDOdb;

echo '<b>------ START ------</b><br /><br />';

// Recherche des nomenclatures liées aux lignes de propal qui n'existent plus
$sql = '
	SELECT rowid FROM '.$db->prefix().'nomenclature
	WHERE object_type = \'propal\'
	AND fk_object NOT IN (SELECT rowid FROM '.$db->prefix().'propaldet)
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
	SELECT rowid FROM '.$db->prefix().'nomenclature
	WHERE object_type = \'product\'
	AND fk_object NOT IN (SELECT rowid FROM '.$db->prefix().'product)
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
