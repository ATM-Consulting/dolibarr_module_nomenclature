<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../class/nomenclature.class.php';

echo '<h1>Nomenclature : script de suppression des doublons</h1>';

$PDOdb = new TPDOdb();

$sql = '
	SELECT pd.fk_propal, pd.rowid as fk_propaldet, pd.rang
	
	FROM ' . MAIN_DB_PREFIX . 'nomenclature n
	INNER JOIN ' . MAIN_DB_PREFIX . 'propaldet pd ON pd.rowid = n.fk_object
	LEFT JOIN ' . MAIN_DB_PREFIX . 'nomenclaturedet nd ON nd.fk_nomenclature = n.rowid
	
	WHERE n.object_type = "propal"
	
	GROUP BY pd.rowid
	HAVING COUNT(DISTINCT n.rowid) > 1
	
	ORDER BY pd.fk_propal ASC, pd.rang ASC
';

$resql = $db->query($sql);

if (! $resql)
{
	_gracefullFail($db);
}

$num = $db->num_rows($resql);

echo '<p><b>' . $num .  '</b> lignes de propales avec des nomenclatures en doublon</p><hr />';

for ($i = 0; $i < $num; $i++)
{
	$obj = $db->fetch_object($resql);

	echo '<p>Propal <b>' . $obj->fk_propal . '</b>, ligne <b>' . $obj->fk_propaldet . '</b> (rang ' . $obj->rang . ')';

	$sqlNomenclature = '
		SELECT n.rowid as fk_nomenclature, COUNT(DISTINCT nd.rowid) as nbDet, n.date_maj

		FROM ' . MAIN_DB_PREFIX .  'nomenclature n
		LEFT JOIN ' . MAIN_DB_PREFIX .  'nomenclaturedet nd ON nd.fk_nomenclature = n.rowid

		WHERE n.object_type = "propal"
		AND n.fk_object = ' . $obj->fk_propaldet . '

		GROUP BY n.rowid

		ORDER BY COUNT(DISTINCT nd.rowid) DESC, n.date_maj DESC
	';

	$resqlNomenclature = $db->query($sqlNomenclature);

	if (! $resqlNomenclature)
	{
		_gracefullFail($db);
	}

	$numNomenclature = $db->num_rows($resqlNomenclature);

	echo ' : <b>'  . $numNomenclature .  ' </b> nomenclatures</p>';

	$loadedNomenclature = new TNomenclature();
	$loadedNomenclature->loadByObjectId($PDOdb, $obj->fk_propaldet, 'propal');

	echo '<ol>';

	for ($j = 0; $j < $numNomenclature; $j++)
	{
		$objNomenclature = $db->fetch_object($resqlNomenclature);

		echo '<li>Nomenclature <b>' . $objNomenclature->fk_nomenclature . '</b>, <b>' . $objNomenclature->nbDet;
		echo '</b> composants, dernière MàJ le <b>' . $objNomenclature->date_maj . '</b> - ';

		if ($objNomenclature->fk_nomenclature == $loadedNomenclature->rowid)
		{
			echo '<b>Affichée</b>';
		}
		else
		{
			echo '<b>Supprimée</b>';
			
			$nomenclatureToDelete = new TNomenclature();
			$nomenclatureToDelete->load($PDOdb, $objNomenclature->fk_nomenclature);
			$nomenclatureToDelete->delete($PDOdb);
		}

		echo '</li>';
	}

	echo '</ol>';
}

$db->free($resql);

$db->close();

echo '<p>Tout est bien qui finit bien</p>';




function _gracefullFail(DoliDB &$db)
{
	echo '<p>Tout est mal qui finit mal</p>';
	
	dol_print_error($db);

	$db->close();

	exit;
}
