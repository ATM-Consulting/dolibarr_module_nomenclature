<?php

	require '../config.php';

	ini_set('memory_limit','1024M');

	dol_include_once('/core/lib/admin.lib.php');
	dol_include_once('/nomenclature/lib/nomenclature.lib.php');
	dol_include_once('/nomenclature/class/nomenclature.class.php');

	dol_include_once('/product/class/product.class.php');

	$langs->load("nomenclature@nomenclature");
	$PDOdb = new TPDOdb;

	// Access control
	if (! $user->admin) {
	    accessforbidden();
	}

	_card($PDOdb);

function _card(&$PDOdb) {
	global $langs, $user;

	llxHeader();

	$head = nomenclatureAdminPrepareHead();
	dol_fiche_head(
	    $head,
	    'import',
	    $langs->trans("Module104580Name"),
	    0,
	    "nomenclature@nomenclature"
	);

	$formCore=new TFormCore('auto','formImport','post',true);

	echo $formCore->fichier('Fichier source'.img_help(1, 'Numéro nomenclature,Ref produit, Ref Composant, Qté, Qté de référence, Code type (MO pour Poste de travail)') , 'file1', '', 40);
	echo $formCore->btsubmit('Voir', 'bt_view');

	_import_to_session();

	_show_tab_session($PDOdb);

	$formCore->end();


	dol_fiche_end();

	llxFooter();
}

function _show_tab_session(&$PDOdb) {
	global $langs,$db, $user;

	$Tab = &$_SESSION['TDataImport'];

	$save = GETPOST('bt_save') ? true : false;
	//var_dump($Tab);
	if (!empty($Tab))
	{

	    $nb_not_here = 0;
		foreach($Tab as $fk_product=>$TNomenclature) {

			$p=new Product($db);
			if($p->fetch(0,$fk_product)<=0) {
			    $nb_not_here++;
			    continue;
			}

			echo '<hr />'.$p->getNomUrl(1).' - '.$p->label;

			foreach($TNomenclature as $TData) {

				$n=new TNomenclature;
				$n->fk_object = $p->id;
				$n->object_type = 'product';

				foreach($TData as $data) {
					if(!empty($data['qty_ref']))$n->qty_reference = (double)$data['qty_ref'];

					if($data['type'] == 'MO') {
						$w = new TWorkstation();
						$w->loadBy($PDOdb, $data['fk_product_composant'], 'code');

						$k = $n->addChild($PDOdb, 'TNomenclatureWorkstation');
						$n->TNomenclatureWorkstation[$k]->fk_workstation = $w->getId();
						$n->TNomenclatureWorkstation[$k]->nb_hour_manufacture = $data['qty'];
						$n->TNomenclatureWorkstation[$k]->rang = $k+1;
						$n->TNomenclatureWorkstation[$k]->workstation = $w;
					} else {
						$p_compo=new Product($db);
						if($p_compo->fetch(0,$data['fk_product_composant'])<=0) continue;

						$k = $n->addChild($PDOdb, 'TNomenclatureDet');
						$n->TNomenclatureDet[$k]->fk_product = $p_compo->id;
						$n->TNomenclatureDet[$k]->qty = $data['qty'];
						$n->TNomenclatureDet[$k]->code_type = $data['type'];
						$n->TNomenclatureDet[$k]->product = $p_compo;
					}

				}

				if($save) $n->save($PDOdb);

				_show_nomenclature($n);

			}

		}

		echo '<p>'.$nb_not_here.' nomenclature(s) non importée(s) car produit(s) non présent(s)</p>';

	}

	if(!$save) {
		$formCore=new TFormCore;
		echo '<div class="tabsAction">';
		echo $formCore->btsubmit('Sauvegarder', 'bt_save');
		echo '</div>';
	}
	else {
		print 'Nomenclatures créées';

	}
}

function _show_nomenclature(&$n) {

	global $langs,$db, $user;

	echo '<br />Pour : '.$n->qty_reference;

	if($n->getId()>0) echo '<br />Id nomenclature créée : '.$n->getId();

	echo '<table class="border" width="100%"><tr class="liste_titre"><td>Type</td><td>Composant</td><td>Qté</td></tr>';

	// Components
	foreach($n->TNomenclatureDet as &$det) {

		echo '<tr>
			<td width="10%">'.$det->code_type.'</td>
			<td width="70%">'.$det->product->getNomUrl(1).' - '.$det->product->label.'</td>
			<td  width="20%" align="right">'.price($det->qty).'</td>
		</tr>';

	}

	// Workstations
	foreach($n->TNomenclatureWorkstation as &$wst) {

		echo '<tr>
			<td width="10%">'.$wst->workstation->code.'</td>
			<td width="70%">'.$wst->workstation->getNomUrl(1).'</td>
			<td  width="20%" align="right">'.$wst->nb_hour_manufacture.'</td>
		</tr>';

	}


	echo '</table>';

}

function _import_to_session() {

	if(GETPOST('bt_view') && !empty($_FILES['file1']['name'])) {
		$Tab = &$_SESSION['TDataImport'];
		$Tab = array();

		$f1 = fopen($_FILES['file1']['tmp_name'],'r');

		if($f1 === false) exit('Houston ? ');

		while(!feof($f1)) {

			$row = fgetcsv($f1, 4096, ',', '"');

			$num_nomenclature = (int)$row[0];
			if(empty($num_nomenclature)) $num_nomenclature = 1;

			$fk_product = trim($row[1]);
			if(empty($fk_product)) continue;

			$fk_product_composant = $row[2]; // produit ou code WS
			if(empty($fk_product_composant)) continue;

			$qty = (double)$row[3];
			$qty_ref = (double)$row[4];
			$type = $row[5];

			if(empty($Tab[$fk_product]))$Tab[$fk_product]=array();
			if(empty($Tab[$fk_product][$num_nomenclature]))$Tab[$fk_product][$num_nomenclature]=array();

			$Tab[$fk_product][$num_nomenclature][]=array(
				'fk_product_composant'=>$fk_product_composant
				,'qty'=>$qty
				,'qty_ref'=>$qty_ref
				,'type'=>$type
			);

		}

		fclose($f1);
	}

}
