<?php
/* Copyright (C) 2013       Antoine Iauch	        <aiauch@gpcsolutions.fr>
 * Copyright (C) 2013-2016  Laurent Destailleur     <eldy@users.sourceforge.net>
 * Copyright (C) 2015       Raphaël Doursenaud      <rdoursenaud@gpcsolutions.fr>
 * Copyright (C) 2018       Frédéric France         <frederic.france@netlogic.fr>
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

/**
 *	   \file		htdocs/compta/stats/cabyprodserv.php
 *	   \brief	   Page reporting TO by Products & Services
 */

require __DIR__ . '/config.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/report.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/tax.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formother.class.php';
require_once DOL_DOCUMENT_ROOT.'/categories/class/categorie.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require __DIR__ . '/class/nomenclature.class.php';

// Load translation files required by the page
$langs->loadLangs(array("products", "categories", "errors", 'accountancy'));

// Security pack (data & check)
$socid = GETPOST('socid', 'int');

if ($user->socid > 0) {
	$socid = $user->socid;
}
if (!empty($conf->comptabilite->enabled)) {
	$result = restrictedArea($user, 'compta', '', '', 'resultat');
}
if (!empty($conf->accounting->enabled)) {
	$result = restrictedArea($user, 'accounting', '', '', 'comptarapport');
}

// Define modecompta ('CREANCES-DETTES' or 'RECETTES-DEPENSES')
$modecompta = $conf->global->ACCOUNTING_MODE;
if (GETPOST("modecompta")) {
	$modecompta = GETPOST("modecompta");
}

$sortorder = GETPOST("sortorder", 'aZ09');
$sortfield = GETPOST("sortfield", 'aZ09');
if (!$sortorder) {
	$sortorder = "asc";
}
if (!$sortfield) {
	$sortfield = "ref";
}

// Category
$selected_cat = (int) GETPOST('search_categ', 'int');
$selected_soc = (int) GETPOST('search_soc', 'int');
$subcat = false;
if (GETPOST('subcat', 'alpha') === 'yes') {
	$subcat = true;
}
$categorie = new Categorie($db);

// product/service
$selected_type = GETPOST('search_type', 'int');
if ($selected_type == '') {
	$selected_type = -1;
}

// Hook
$hookmanager->initHooks(array('cabyprodservlist'));

// Date range
$year = GETPOST("year");
$month = GETPOST("month");
$date_startyear = GETPOST("date_startyear");
$date_startmonth = GETPOST("date_startmonth");
$date_startday = GETPOST("date_startday");
$date_endyear = GETPOST("date_endyear");
$date_endmonth = GETPOST("date_endmonth");
$date_endday = GETPOST("date_endday");
if (empty($year)) {
	$year_current = dol_print_date(dol_now(), '%Y');
	$month_current = dol_print_date(dol_now(), '%m');
	$year_start = $year_current;
} else {
	$year_current = $year;
	$month_current = dol_print_date(dol_now(), '%m');
	$year_start = $year;
}
$date_start = dol_mktime(0, 0, 0, GETPOST("date_startmonth"), GETPOST("date_startday"), GETPOST("date_startyear"), 'tzserver');	// We use timezone of server so report is same from everywhere
$date_end = dol_mktime(23, 59, 59, GETPOST("date_endmonth"), GETPOST("date_endday"), GETPOST("date_endyear"), 'tzserver');		// We use timezone of server so report is same from everywhere
// Quarter
if (empty($date_start) || empty($date_end)) { // We define date_start and date_end
	$q = GETPOST("q", "int");
	if (empty($q)) {
		// We define date_start and date_end
		$month_start = GETPOST("month") ?GETPOST("month") : ($conf->global->SOCIETE_FISCAL_MONTH_START ? ($conf->global->SOCIETE_FISCAL_MONTH_START) : 1);
		$year_end = $year_start;
		$month_end = $month_start;
		if (!GETPOST("month")) {	// If month not forced
			if (!GETPOST('year') && $month_start > $month_current) {
				$year_start--;
				$year_end--;
			}
			$month_end = $month_start - 1;
			if ($month_end < 1) {
				$month_end = 12;
			} else {
				$year_end++;
			}
		}
		$date_start = dol_get_first_day($year_start, $month_start, false);
		$date_end = dol_get_last_day($year_end, $month_end, false);
	} else {
		if ($q == 1) {
			$date_start = dol_get_first_day($year_start, 1, false);
			$date_end = dol_get_last_day($year_start, 3, false);
		}
		if ($q == 2) {
			$date_start = dol_get_first_day($year_start, 4, false);
			$date_end = dol_get_last_day($year_start, 6, false);
		}
		if ($q == 3) {
			$date_start = dol_get_first_day($year_start, 7, false);
			$date_end = dol_get_last_day($year_start, 9, false);
		}
		if ($q == 4) {
			$date_start = dol_get_first_day($year_start, 10, false);
			$date_end = dol_get_last_day($year_start, 12, false);
		}
	}
} else {
	// TODO We define q
}

// $date_start and $date_end are defined. We force $year_start and $nbofyear
$tmps = dol_getdate($date_start);
$year_start = $tmps['year'];
$tmpe = dol_getdate($date_end);
$year_end = $tmpe['year'];
$nbofyear = ($year_end - $year_start) + 1;

$commonparams = array();
if (!empty($modecompta)) {
	$commonparams['modecompta'] = $modecompta;
}
if (!empty($sortorder)) {
	$commonparams['sortorder'] = $sortorder;
}
if (!empty($sortfield)) {
	$commonparams['sortfield'] = $sortfield;
}

$headerparams = array();
if (!empty($date_startyear)) {
	$headerparams['date_startyear'] = $date_startyear;
}
if (!empty($date_startmonth)) {
	$headerparams['date_startmonth'] = $date_startmonth;
}
if (!empty($date_startday)) {
	$headerparams['date_startday'] = $date_startday;
}
if (!empty($date_endyear)) {
	$headerparams['date_endyear'] = $date_endyear;
}
if (!empty($date_endmonth)) {
	$headerparams['date_endmonth'] = $date_endmonth;
}
if (!empty($date_endday)) {
	$headerparams['date_endday'] = $date_endday;
}
if (!empty($year)) {
	$headerparams['year'] = $year;
}
if (!empty($month)) {
	$headerparams['month'] = $month;
}
$headerparams['q'] = $q;

$tableparams = array();
if (!empty($selected_cat)) {
	$tableparams['search_categ'] = $selected_cat;
}
if (!empty($selected_soc)) {
	$tableparams['search_soc'] = $selected_soc;
}
if (!empty($selected_type)) {
	$tableparams['search_type'] = $selected_type;
}
$tableparams['subcat'] = ($subcat === true) ? 'yes' : '';

// Adding common parameters
$allparams = array_merge($commonparams, $headerparams, $tableparams);
$headerparams = array_merge($commonparams, $headerparams);
$tableparams = array_merge($commonparams, $tableparams);

foreach ($allparams as $key => $value) {
	$paramslink .= '&'.$key.'='.$value;
}


/*
 * View
 */

llxHeader();

$form = new Form($db);
$formother = new FormOther($db);

// TODO Report from bookkeeping not yet available, so we switch on report on business events
if ($modecompta == "BOOKKEEPING") {
	$modecompta = "CREANCES-DETTES";
}
if ($modecompta == "BOOKKEEPINGCOLLECTED") {
	$modecompta = "RECETTES-DEPENSES";
}

// Show report header
if ($modecompta == "CREANCES-DETTES") {
	$name = $langs->trans("Turnover").', '.$langs->trans("ByProductsAndServices");
	$calcmode = $langs->trans("CalcModeDebt");
	//$calcmode.='<br>('.$langs->trans("SeeReportInInputOutputMode",'<a href="'.$_SERVER["PHP_SELF"].'?year='.$year_start.'&modecompta=RECETTES-DEPENSES">','</a>').')';

	$description = $langs->trans("RulesCADue");
	if (!empty($conf->global->FACTURE_DEPOSITS_ARE_JUST_PAYMENTS)) {
		$description .= $langs->trans("DepositsAreNotIncluded");
	} else {
		$description .= $langs->trans("DepositsAreIncluded");
	}

	$builddate = dol_now();
} elseif ($modecompta == "RECETTES-DEPENSES") {
	$name = $langs->trans("TurnoverCollected").', '.$langs->trans("ByProductsAndServices");
	$calcmode = $langs->trans("CalcModeEngagement");
	//$calcmode.='<br>('.$langs->trans("SeeReportInDueDebtMode",'<a href="'.$_SERVER["PHP_SELF"].'?year='.$year_start.'&modecompta=CREANCES-DETTES">','</a>').')';

	$description = $langs->trans("RulesCAIn");
	$description .= $langs->trans("DepositsAreIncluded");

	$builddate = dol_now();
} elseif ($modecompta == "BOOKKEEPING") {
} elseif ($modecompta == "BOOKKEEPINGCOLLECTED") {
}

$period = $form->selectDate($date_start, 'date_start', 0, 0, 0, '', 1, 0, 0, '', '', '', '', 1, '', '', 'tzserver');
$period .= ' - ';
$period .= $form->selectDate($date_end, 'date_end', 0, 0, 0, '', 1, 0, 0, '', '', '', '', 1, '', '', 'tzserver');
if ($date_end == dol_time_plus_duree($date_start, 1, 'y') - 1) {
	$periodlink = '<a href="'.$_SERVER["PHP_SELF"].'?year='.($year_start - 1).'&modecompta='.$modecompta.'">'.img_previous().'</a> <a href="'.$_SERVER["PHP_SELF"].'?year='.($year_start + 1).'&modecompta='.$modecompta.'">'.img_next().'</a>';
} else {
	$periodlink = '';
}

report_header($name, $namelink, $period, $periodlink, $description, $builddate, $exportlink, $tableparams, $calcmode);

if (!empty($conf->accounting->enabled) && $modecompta != 'BOOKKEEPING') {
	print info_admin($langs->trans("WarningReportNotReliable"), 0, 0, 1);
}



$name = array();

// SQL request
$catotal = 0;
$catotal_ht = 0;
$qtytotal = 0;

if ($modecompta == 'CREANCES-DETTES') {
	$sql = "SELECT l.rowid as rowid, p.ref as ref, p.label as label, p.fk_product_type as product_type,";
	$sql .= " l.total_ht as amount, l.total_ttc as amount_ttc,";
	$sql .= " CASE WHEN f.type = 2 THEN -l.qty ELSE l.qty END as qty";

	$parameters = array();
	$hookmanager->executeHooks('printFieldListSelect', $parameters);
	$sql .= $hookmanager->resPrint;

	$sql .= " FROM ".MAIN_DB_PREFIX."facture as f";
	if ($selected_soc > 0) {
		$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe as soc ON (soc.rowid = f.fk_soc)";
	}
	$sql .= ",".MAIN_DB_PREFIX."facturedet as l";
	$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."product as p ON l.fk_product = p.rowid";
	if ($selected_cat === -2) {	// Without any category
		$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."categorie_product as cp ON p.rowid = cp.fk_product";
	}

	$parameters = array();
	$hookmanager->executeHooks('printFieldListFrom', $parameters);
	$sql .= $hookmanager->resPrint;

	$sql .= " WHERE l.fk_facture = f.rowid";
	$sql .= " AND f.fk_statut in (1,2)";
	$sql .= " AND l.product_type in (0,1)";
	if (!empty($conf->global->FACTURE_DEPOSITS_ARE_JUST_PAYMENTS)) {
		$sql .= " AND f.type IN (0,1,2,5)";
	} else {
		$sql .= " AND f.type IN (0,1,2,3,5)";
	}
	if ($date_start && $date_end) {
		$sql .= " AND f.datef >= '".$db->idate($date_start)."' AND f.datef <= '".$db->idate($date_end)."'";
	}
	// Le filtre est maintenant fait en PHP, obligatoire à cause de la récursivité
	/*if ($selected_type >= 0) {
		$sql .= " AND l.product_type = ".((int) $selected_type);
	}*/
//	if ($selected_cat === -2) {	// Without any category
//		$sql .= " AND cp.fk_product is null";
//	} elseif ($selected_cat > 0) {	// Into a specific category
//		if ($subcat) {
//			$TListOfCats = $categorie->get_full_arbo('product', $selected_cat, 1);
//
//			$listofcatsql = "";
//			foreach ($TListOfCats as $key => $cat) {
//				if ($key !== 0) {
//					$listofcatsql .= ",";
//				}
//				$listofcatsql .= $cat['rowid'];
//			}
//		}
//
//		$sql .= " AND (p.rowid IN ";
//		$sql .= " (SELECT fk_product FROM ".MAIN_DB_PREFIX."categorie_product cp WHERE ";
//		if ($subcat) {
//			$sql .= "cp.fk_categorie IN (".$db->sanitize($listofcatsql).")";
//		} else {
//			$sql .= "cp.fk_categorie = ".((int) $selected_cat);
//		}
//		$sql .= "))";
//	}
	if ($selected_soc > 0) {
		$sql .= " AND soc.rowid=".((int) $selected_soc);
	}
	$sql .= " AND f.entity IN (".getEntity('invoice').")";

	$parameters = array();
	$hookmanager->executeHooks('printFieldListWhere', $parameters);
	$sql .= $hookmanager->resPrint;

	$sql .= " GROUP BY l.rowid, p.ref, p.label, p.fk_product_type";
	//$sql .= $db->order($sortfield, $sortorder); Le tri est maintenant fait en PHP, obligatoire à cause de la récursivité
//echo $sql;
	dol_syslog("cabynomenclature", LOG_DEBUG);
	$result = $db->query($sql);
	if ($result) {
		$num = $db->num_rows($result);
		$i = 0;
		while ($i < $num) {
			$obj = $db->fetch_object($result);
			$amount_ht[$obj->rowid] = $obj->amount;
			$amount[$obj->rowid] = $obj->amount_ttc;
			$qty[$obj->rowid] = $obj->qty;
			$name[$obj->rowid] = $obj->ref.'&nbsp;-&nbsp;'.$obj->label;
			$type[$obj->rowid] = $obj->product_type;
			$catotal_ht += $obj->amount;
			$catotal += $obj->amount_ttc;
			$qtytotal += $obj->qty;
			$i++;
		}
	} else {
		dol_print_error($db);
	}

	// Show Array
	$i = 0;
	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">'."\n";
	// Extra parameters management
	foreach ($headerparams as $key => $value) {
		print '<input type="hidden" name="'.$key.'" value="'.$value.'">';
	}

	$moreforfilter = '';

	print '<div class="div-table-responsive">';
	print '<table class="tagtable liste'.($moreforfilter ? " listwithfilterbefore" : "").'">'."\n";

	// Category filter
	print '<tr class="liste_titre">';
	print '<td>';
	print img_picto('', 'category', 'class="paddingrightonly"');
	print $formother->select_categories(Categorie::TYPE_PRODUCT, $selected_cat, 'search_categ', 0, $langs->trans("Category"));
	print ' ';
	print $langs->trans("SubCats").'? ';
	print '<input type="checkbox" name="subcat" value="yes"';
	if ($subcat) {
		print ' checked';
	}
	print '>';
	// type filter (produit/service)
	print ' ';
	print $langs->trans("Type").': ';
	$form->select_type_of_lines(isset($selected_type) ? $selected_type : -1, 'search_type', 1, 1, 1);

	//select thirdparty
	print '</br>';
	print img_picto('', 'company', 'class="paddingrightonly"');
	print $form->select_thirdparty_list($selected_soc, 'search_soc', '', $langs->trans("ThirdParty"));
	print '</td>';

	print '<td colspan="5" class="right">';
	print '<input type="image" class="liste_titre" name="button_search" src="'.img_picto($langs->trans("Search"), 'search.png', '', '', 1).'"  value="'.dol_escape_htmltag($langs->trans("Search")).'" title="'.dol_escape_htmltag($langs->trans("Search")).'">';

	$parameters = array();
	$reshook = $hookmanager->executeHooks('printFieldListeTitle', $parameters);
	print $hookmanager->resPrint;

	print '</td></tr>';

	// Array header
	print "<tr class=\"liste_titre\">";
	print_liste_field_titre(
		$langs->trans("Product"),
		$_SERVER["PHP_SELF"],
		"ref",
		"",
		$paramslink,
		"",
		$sortfield,
		$sortorder
	);
	print_liste_field_titre(
		$langs->trans('Quantity'),
		$_SERVER["PHP_SELF"],
		"qty",
		"",
		$paramslink,
		'class="right"',
		$sortfield,
		$sortorder
	);
	print_liste_field_titre(
		$langs->trans("Percentage"),
		$_SERVER["PHP_SELF"],
		"qty",
		"",
		$paramslink,
		'class="right"',
		$sortfield,
		$sortorder
	);
	print_liste_field_titre(
		$langs->trans('AmountHT'),
		$_SERVER["PHP_SELF"],
		"amount",
		"",
		$paramslink,
		'class="right"',
		$sortfield,
		$sortorder
	);
	print_liste_field_titre(
		$langs->trans("AmountTTC"),
		$_SERVER["PHP_SELF"],
		"amount_ttc",
		"",
		$paramslink,
		'class="right"',
		$sortfield,
		$sortorder
	);
	print_liste_field_titre(
		$langs->trans("Percentage"),
		$_SERVER["PHP_SELF"],
		"amount_ttc",
		"",
		$paramslink,
		'class="right"',
		$sortfield,
		$sortorder
	);
	print "</tr>\n";

	if (count($name)) {
		setAmountsByNomenclature($sortfield, $selected_type, $selected_cat, $subcat);
		foreach ($name as $key => $value) {
			print '<tr class="oddeven">';

			// Product
			print "<td>";
			$fullname = $name[$key];
			if ($key > 0) {
				$linkname = '<a href="'.DOL_URL_ROOT.'/product/card.php?id='.$key.'">'.img_object($langs->trans("ShowProduct"), $type[$key] == 0 ? 'product' : 'service').' '.$fullname.'</a>';
			} else {
				$linkname = $langs->trans("PaymentsNotLinkedToProduct");
			}
			print $linkname;
			print "</td>\n";

			// Quantity
			print '<td class="right">';
			print $qty[$key];
			print '</td>';

			// Percent;
			print '<td class="right">'.($qtytotal > 0 ? round(100 * $qty[$key] / $qtytotal, 2).'%' : '&nbsp;').'</td>';

			// Amount w/o VAT
			print '<td class="right">';
			/*if ($key > 0) {
				print '<a href="'.DOL_URL_ROOT.'/compta/facture/list.php?productid='.$key.'">';
			} else {
				print '<a href="#">';
			}*/
			print price($amount_ht[$key]);
			//print '</a>';
			print '</td>';

			// Amount with VAT
			print '<td class="right">';
			/*if ($key > 0) {
				print '<a href="'.DOL_URL_ROOT.'/compta/facture/list.php?productid='.$key.'">';
			} else {
				print '<a href="#">';
			}*/
			print price($amount[$key]);
			//print '</a>';
			print '</td>';

			// Percent;
			print '<td class="right">'.($catotal > 0 ? round(100 * $amount[$key] / $catotal, 2).'%' : '&nbsp;').'</td>';

			// TODO: statistics?

			print "</tr>\n";
			$i++;
		}

		// Total
		print '<tr class="liste_total">';
		print '<td>'.$langs->trans("Total").'</td>';
		print '<td class="right">'.$qtytotal.'</td>';
		print '<td class="right">100%</td>';
		print '<td class="right">'.price($catotal_ht).'</td>';
		print '<td class="right">'.price($catotal).'</td>';
		print '<td class="right">100%</td>';
		print '</tr>';

		$db->free($result);
	}
	print "</table>";
	print '</div>';

	print '</form>';
} else {
	// $modecompta != 'CREANCES-DETTES'
	// "Calculation of part of each product for accountancy in this mode is not possible. When a partial payment (for example 5 euros) is done on an
	// invoice with 2 product (product A for 10 euros and product B for 20 euros), what is part of paiment for product A and part of paiment for product B ?
	// Because there is no way to know this, this report is not relevant.
	print '<br>'.$langs->trans("TurnoverPerProductInCommitmentAccountingNotRelevant").'<br>';
}

// End of page
llxFooter();
$db->close();

/**
 * Fonction permettant de modifier les valeurs de tous les tableaux nécessaires à l'affichage des données en fonction du détail des nomenclatures / ouvrages
 *
 * @param $sortfield string sortfield
 * @return void
 */
function setAmountsByNomenclature($sortfield, $selected_type, $selected_cat, $subcat) {

	global $db, $name, $amount_ht, $amount, $qty, $type, $catotal_ht, $catotal, $qtytotal;

	$PDOdb = new TPDOdb;

	$TRes=array();

	// On parcoure les lignes de factures concernées par le période via le tableau $name
	foreach ($name as $id_line=>$label) {

		$line = new FactureLigne($db);
		$line->fetch($id_line);

		$fac = new stdClass();
		$fac->element = 'facture';
		$fac->id = $line->fk_facture;

		$n = new TNomenclature;
		$res=$n->loadByObjectId($PDOdb, $line->id, 'facture');

		if(!empty($n) && !empty($n->TNomenclatureDet)) { // Il existe une nomenclature pour la ligne de facture

			TNomenclature::getMarginDetailByProductAndService($PDOdb, $fac, $TRes, $n, $line->qty, $line, true);

		} else { // C'est un produit ou service sans nomenclature associée

			$TRes[$line->fk_product]['pv'] += $line->total_ht;
			$TRes[$line->fk_product]['pv_ttc'] += $line->total_ttc;
			$sign = $line->subprice < 0 ? '-' : '+';
			$TRes[$line->fk_product]['qty'] += $sign.$line->qty;
			$TRes[$line->fk_product]['type'] = $line->product_type;

			$p = new Product($db);
			$p->fetch($line->fk_product);

			$TRes[$line->fk_product]['label'] = $p->ref.'&nbsp;-&nbsp;'.$p->label;
		}

	}

	$name = $amount_ht = $amount = $qty = $type = $TLoadedCategs = array();
	$qtytotal=$catotal=$catotal_ht=0;


	// On regroupe les informations par produit / service
	foreach ($TRes as $id_prod => $TData) {

		// Filtre type produit / service
		if($selected_type >= 0 && $TData['type'] !== $selected_type) continue;

		// Filtre categ
		if($selected_cat > 0) {
			$categorie = new Categorie($db);
			if ($selected_cat === -2) {    // Without any category
				$sql .= " AND cp.fk_product is null";
			} elseif ($selected_cat > 0) {    // Into a specific category
				if ($subcat) {
					$TListOfCats = $categorie->get_full_arbo('product', $selected_cat, 1);

					$product_is_present_in_one_categ=false;
					foreach ($TListOfCats as $key => $cat) {
						if ($key === 0) continue;

						if(!empty($TLoadedCategs[$cat['rowid']])) $categ = $TLoadedCategs[$cat['rowid']];
						else {
							$categ = new Categorie($db);
							$categ->fetch($cat['rowid']);
							$TLoadedCategs[$cat['rowid']] = $categ;
						}

						if($categ->containsObject('product', $id_prod) > 0) {
							$product_is_present_in_one_categ=true;
							break;
						}
					}

					if(empty($product_is_present_in_one_categ)) continue;

				} else {

					if(!empty($TLoadedCategs[$selected_cat])) $categ = $TLoadedCategs[$selected_cat];
					else {
						$categ = new Categorie($db);
						$categ->fetch($selected_cat);
						$TListOfCats[$selected_cat] = $categ;
					}

					$res = $categ->containsObject('product', $id_prod);
					if(empty($res)) continue;

				}
			}
		}

		$tooltip=array();
		if(!empty($TData['tooltip'])) {
			foreach ($TData['tooltip'] as $ouvrage=>$nb_sell) {
				$tooltip[$nb_sell.' x '.$ouvrage] = $nb_sell.' x '.$ouvrage;
			}
		}

		$name[$id_prod] = $TData['label'].(!empty($TData['tooltip']) ? '&nbsp;'.img_info(implode(", ", $tooltip)) : '');
		$qty[$id_prod] = $TData['qty'];
		$qtytotal += $TData['qty'];
		$amount_ht[$id_prod] += $TData['pv'];
		$amount[$id_prod] += $TData['pv_ttc'];
		$catotal_ht += price2num($TData['pv'], 'MT');
		$catotal += price2num($TData['pv_ttc'], 'MT');
		$type[$id_prod] = $TData['type'];

	}

	// Gestion des tris
	if(!empty($sortfield)) {

		// Tri par valeur en fonction du tableauégalement trier
		if($sortfield === 'ref') $array_to_sort = &$name;
		elseif($sortfield === 'qty') $array_to_sort = &$qty;
		elseif($sortfield === 'amount') $array_to_sort = $amount_ht;
		elseif($sortfield === 'amount_ttc') $array_to_sort = $amount;
		uasort($array_to_sort, '_cmp');

		// La boucle pour l'affichage se base sur l'ordre des éléments du tableau $name, donc si on trie une autre colonne, il faut réordonner le tableau $name dans ce sens pour que l'affichage soit juste
		if($sortfield !== 'name') _tri_tableau_name_by_order_other_tableau($name,$array_to_sort);
	}

}

/**
 * Fonction de tri pour uasort
 *
 * @param $a int or string first element to compare
 * @param $b int or string second element to compare
 * @return int
 */
function _cmp($a, $b) {

	global $sortorder;

	if(!is_numeric($a)) {
		if($sortorder === 'asc') {
			return strcmp($a, $b);
		} else {
			return strcmp($b, $a);
		}
	}

	if ($a == $b) {
        return 0;
    }

	if($sortorder === 'asc') {
		return ($a < $b) ? -1 : 1;
	} else {
		return ($a > $b) ? -1 : 1;
	}

}

/**
 * @param $name array array of products names
 * @param $other_array array another array (qty, amount_ht or amount)
 * @return void
 */
function _tri_tableau_name_by_order_other_tableau(&$name, &$other_array) {

	$tmp = array();

	foreach ($other_array as $k=>$v) {
		$tmp[$k] = $name[$k];
	}

	$name = $tmp;

}
