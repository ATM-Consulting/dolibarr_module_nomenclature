<?php

	require '../config.php';

	ini_set('memory_limit','1024M');

	dol_include_once('/core/lib/admin.lib.php');
	dol_include_once('/nomenclature/lib/nomenclature.lib.php');
	dol_include_once('/nomenclature/class/nomenclature.class.php');
	dol_include_once('abricot/includes/lib/admin.lib.php');
	dol_include_once('/product/class/product.class.php');

	$langs->load("nomenclature@nomenclature");
	$PDOdb = new TPDOdb;

	// Access control
	if (! $user->admin) {
	    accessforbidden();
	}

	$action = GETPOST('action');

/**
 * ACTION
 */

	if($action == 'cancel'){
		unset($_SESSION['TDataImport']);
	}

	if (preg_match('/set_(.*)/',$action,$reg))
	{
		$code=$reg[1];
		if (dolibarr_set_const($db, $code, GETPOST($code, 'none'), 'chaine', 0, '', $conf->entity) > 0)
		{
			setEventMessage($langs->trans("ParamSaved"));
			header("Location: ".$_SERVER["PHP_SELF"]);
			exit;
		}
		else
		{
			dol_print_error($db);
		}

	}

	if (preg_match('/del_(.*)/',$action,$reg))
	{
		$code=$reg[1];
		if (dolibarr_del_const($db, $code, 0) > 0)
		{
			Header("Location: ".$_SERVER["PHP_SELF"]);
			exit;
		}
		else
		{
			dol_print_error($db);
		}
	}

/**
 * VIEW
 */
	_card($PDOdb);

function _card(&$PDOdb) {
	global $langs, $user, $conf;

	$page_name = "nomenclatureSetup";
	llxHeader('', $langs->trans($page_name));

	// Subheader
	$linkback = '<a href="' . DOL_URL_ROOT . '/admin/modules.php">'
		. $langs->trans("BackToModuleList") . '</a>';
	print load_fiche_titre($langs->trans($page_name), $linkback);

	$head = nomenclatureAdminPrepareHead();
	dol_fiche_head(
	    $head,
	    'import',
	    $langs->trans("Module104580Name"),
	    -1,
	    "nomenclature@nomenclature"
	);
	dol_fiche_end(-1);

	print '<fieldset>';
	print '<legend><strong>'.$langs->trans('NomenclatureImportTitle').'</strong></legend>';

	//déterminer le séparateur du fichier d'import
	if(empty($conf->global->NOMENCLATURE_IMPORT_SEPARATOR)) $conf->global->NOMENCLATURE_IMPORT_SEPARATOR = ',';
	setup_print_input_form_part('NOMENCLATURE_IMPORT_SEPARATOR');

	$formCore=new TFormCore('auto','formImport','post',true);

	echo $formCore->fichier($langs->trans('importFile').img_help(1, $langs->trans('helpNomenclatureImportFile')) , 'file1', '', 40);
	echo $formCore->btsubmit($langs->trans('ImportButton'), 'bt_view', '','button');

	print '</fieldset>';


	_import_to_session();

	_show_tab_session($PDOdb);

	$formCore->end();


	print '<fieldset>';
	print '<legend><em><i class="fa fa-question-circle"></i> '.$langs->trans('HowToImportBom').'</em></legend>';
	$demoImportFileUrl = dol_buildpath('nomenclature/nomenclature_modele_import.csv',1);
	print '<p>'.$langs->trans('HowToImportBomHelp', $demoImportFileUrl).'</p>';

	$TCols = array(
		array(
			'label' => $langs->trans('colNumberOfBom').'*',
			'desc'	=> $langs->trans('colNumberOfBomHelp')
		)
		,array(
			'label' => $langs->trans('colProductRef').'*',
			'desc'	=> $langs->trans('colProductRefHelp')
		)
		,array(
			'label' => $langs->trans('colAssetRef'),
			'desc'	=> $langs->trans('colAssetRefHelp')
		)
		,array(
			'label' => $langs->trans('colAssetType').'*',
			'desc'	=> $langs->trans('colAssetTypeHelp')
		)
		,array(
			'label' => $langs->trans('colAssetQuality'),
			'desc'	=> $langs->trans('colAssetQualityHelp')
		)
		,array(
			'label' => $langs->trans('colQuantityRef'),
			'desc'	=> $langs->trans('colQuantityRefHelp')
		)
		,array(
			'label' => $langs->trans('colMargeToApply'),
			'desc'	=> $langs->trans('colMargeToApplyHelp')
		)
		,array(
			'label' => $langs->trans('colFinalMargeToApply'),
			'desc'	=> $langs->trans('colFinalMargeToApplyHelp')
		)
	);


	print '<table>';
	foreach ($TCols as $TCol){
		print '<tr><th style="text-align: left;">'.$TCol['label'].'</th><td>'.$TCol['desc'].'</td></tr>';
	}
	print '</table>';
	print '<p>'.$langs->trans('ImportBomNoteHelp').'</p>';

	print '</fieldset>';


	llxFooter();
}

function _show_tab_session(&$PDOdb) {
	global $langs,$db, $user;

	$Tab = &$_SESSION['TDataImport'];

	$save = GETPOST('bt_save', 'none') ? true : false;
	//var_dump($Tab);
	if (!empty($Tab))
	{
		$TRefNotFound=array();
		$TRefComposantNotFound=array();
	    $nb_not_here = 0;
		$nb_not_here_composant = 0;
		foreach($Tab as $product_ref=> $TNomenclature) {

			$p=new Product($db);
			if($p->fetch(0,$product_ref)<=0) {
			    $nb_not_here++;
				$TRefNotFound[] = $product_ref;
			    continue;
			}


			foreach($TNomenclature as $TData) {

				$n=new TNomenclature;
				$n->fk_object = $p->id;
				$n->object_type = 'product';
				$nocreate = 0;

				foreach($TData as $data) {
					if(!empty($data['qty_ref']))$n->qty_reference = (double)$data['qty_ref'];

					if(empty($data['fk_product_composant'])) continue;

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
						if($p_compo->fetch(0,$data['fk_product_composant'])<=0){
							setEventMessage($langs->trans('ErrorFetching',$data['fk_product_composant']));
							$nb_not_here_composant++;
							$TRefComposantNotFound[$product_ref] = $data['fk_product_composant'];
							$nocreate = 1;
							continue;
						}
						$k = $n->addChild($PDOdb, 'TNomenclatureDet');
						$n->TNomenclatureDet[$k]->fk_product = $p_compo->id;
						$n->TNomenclatureDet[$k]->qty = $data['qty'];
						$n->TNomenclatureDet[$k]->code_type = $data['type'];
						$n->TNomenclatureDet[$k]->product = $p_compo;
					}

				}

				if($save && empty($nocreate)) {
					$res = $n->save($PDOdb);
					if($res<1){
						unset($_SESSION['TDataImport']);
					}
				}

				if(empty($nocreate)) _show_nomenclature($n, $p);

			}

			print '</fieldset>';
		}

		if ($nb_not_here > 0)
		{
			echo '<div class="error">';
			echo '<p>'.$nb_not_here.' nomenclature(s) non importée(s) car produit(s) non présent(s)</p>';
			echo '<ul>';
			foreach ($TRefNotFound as $k => $ref)
			{
				echo '<li>'.$ref.'</li>';
			}
			echo '</ul>';


			echo '<p>'.$nb_not_here_composant.' nomenclature(s) au(x) composant(s) non présent(s)</p>';
			echo '<ul>';
			foreach ($TRefComposantNotFound as $refproduct => $refcomposant)
			{
				echo '<li> Produit '.$refproduct.' : composant '.$refcomposant.'</li>';
			}
			echo '</ul>';
			echo '</div>';
		}

		if(!$save) {
			$formCore=new TFormCore;
			echo '<div class="tabsAction">';
			echo $formCore->btsubmit('Sauvegarder', 'bt_save', '','butAction');
			if(function_exists('dolGetButtonAction')){
				$url = dol_buildpath('nomenclature/admin/import.php',1).'?action=cancel';
				print dolGetButtonAction($langs->trans('Cancel'), '', 'default', $url);
			}
			echo '</div>';
		}
		else {
			print '<div class="info" >'.$langs->trans('BomsCreated').'</div>';
		}
	}


}

/**
 * @param TNomenclature $n
 */
function _show_nomenclature(&$n, &$p) {

	global $langs,$db, $user;

	print '<fieldset>';
	print '<legend><strong>'.$p->getNomUrl(1).' - '.$p->label.'</strong></legend>';

	echo '<br />Pour : '.$n->qty_reference;

	if($n->getId()>0){
		$url = dol_buildpath('nomenclature/nomenclature.php',1).'?fk_nomenclature='.$n->getId().'&fk_product='.$n->fk_object.'#nomenclature'.$n->getId();
		print '<br />'.$langs->trans('IdOfBommCreated').' : <a href="'.$url.'" >'.$n->getId().'</a>';
	}

	echo '<div class="div-table-responsive" ><table class="border liste centpercent" width="100%"><tr class="liste_titre"><td>'.$langs->trans('Type').'</td><td>'.$langs->trans('Component').'</td><td>'.$langs->trans('Qté').'</td></tr>';

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


	echo '</table></div>';

    flush();
}

function _import_to_session() {

	global $conf;

	if(GETPOST('bt_view', 'none') && !empty($_FILES['file1']['name'])) {
		$Tab = &$_SESSION['TDataImport'];
		$Tab = array();

		$f1 = fopen($_FILES['file1']['tmp_name'],'r');

		if($f1 === false) exit('Houston ? ');

		while(!feof($f1)) {

			$row = fgetcsv($f1, 4096, !empty($conf->global->NOMENCLATURE_IMPORT_SEPARATOR) ? $conf->global->NOMENCLATURE_IMPORT_SEPARATOR : ',', '"');

			$num_nomenclature = (int)$row[0];
			if(empty($num_nomenclature)) $num_nomenclature = 1;

			$fk_product = trim($row[1]);
			if(empty($fk_product)) continue;

			$fk_product_composant = $row[2]; // produit ou code WS
			if(empty($fk_product_composant)) continue;

			$type = $row[3];
			$qty = (double) price2num($row[4]);
			$qty_ref = (double) price2num($row[5]);

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
