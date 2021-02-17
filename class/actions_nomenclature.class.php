<?php
/* <one line to give the program's name and a brief idea of what it does.>
 * Copyright (C) 2015 ATM Consulting <support@atm-consulting.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * \file    class/actions_nomenclature.class.php
 * \ingroup nomenclature
 * \brief   This file is an example hook overload class file
 *          Put some comments here
 */

/**
 * Class Actionsnomenclature
 */
class Actionsnomenclature
{
	/**
	 * @var array Hook results. Propagated to $hookmanager->resArray for later reuse
	 */
	public $results = array();

	/**
	 * @var string String displayed by executeHook() immediately after return
	 */
	public $resprints;

	/**
	 * @var array Errors
	 */
	public $errors = array();

	/**
	 * Constructor
	 */
	public function __construct()
	{
	}

	/**
	 * Overloading the doActions function : replacing the parent's function with the one below
	 *
	 * @param   array()         $parameters     Hook metadatas (context, etc...)
	 * @param   CommonObject    &$object        The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param   string          &$action        Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
    function doActions($parameters, &$object, &$action, $hookmanager) {
        global $conf, $db;

        $TContext = explode(':', $parameters['context']);

        if(in_array('propalcard', $TContext) || in_array('ordercard', $TContext)) {
            if($action == 'nomenclatureUpdateCoeff' && $object->statut == 0) {
                if(! $conf->subtotal->enabled) return 0;    // Inutile de faire quoi que ce soit vu qu'on a besoin d'un titre...

                if(! function_exists('_updateObjectLine')) dol_include_once('/nomenclature/lib/nomenclature.lib.php');
                if(! class_exists('TNomenclatureCoefObject')) dol_include_once('/nomenclature/class/nomenclature.class.php');
                if(! class_exists('TSubtotal')) dol_include_once('/subtotal/class/subtotal.class.php');

                $PDOdb = new TPDOdb;

                $titleLineId = GETPOST('fk_line', 'int');

                // On récupère les lignes appartenant au titre sur lequel on a cliqué (récursivement)
                $TLine = TSubtotal::getLinesFromTitleId($object, $titleLineId, true);
                foreach($TLine as $line) {
                    if(TSubtotal::isTitle($line)) {
                        // On met à jour les extrafields des titres correspondant aux coefficiants
                        $TCoef = TNomenclatureCoef::loadCoef($PDOdb);
                        foreach($TCoef as $coef) {
                            $line->array_options['options_'.$coef->code_type] = GETPOST($coef->code_type, 'none');
                        }
                        $line->insertExtraFields();
                    }
                    else if(! TSubtotal::isModSubtotalLine($line)) {
                        $n = new TNomenclature;
                        $n->loadByObjectId($PDOdb, $line->id, $object->element, true, $line->fk_product, $line->qty, $object->id);
                        $n->fetchCombinedDetails($PDOdb);

                        foreach($n->TNomenclatureDetCombined as $fk_product => $det) {
                            // On récupère les coeffs qu'il faut pour chaque ligne de nomenclature
                            $tx_custom = GETPOST($det->code_type, 'int');
                            $tx_custom2 = GETPOST($det->code_type2, 'int');

                            $shouldISave = false;
                            if($det->tx_custom != $tx_custom) {
                                $det->tx_custom = $tx_custom;
                                $shouldISave = true;
                            }
                            if($det->tx_custom2 != $tx_custom2) {
                                $det->tx_custom2 = $tx_custom2;
                                $shouldISave = true;
                            }
                            if($shouldISave) $det->save($PDOdb);
                        }

                        $n->save($PDOdb);
                        $n->setPrice($PDOdb, $line->qty, null, $object->element, $object->id);

                        _updateObjectLine($n, $object->element, $line->id, $object->id, true);
                    }
                }

                if($object->element == 'propal') $titleLine = new PropaleLigne($db);
                else $titleLine = new OrderLine($db);
                $titleLine->fetch($titleLineId);
                if(empty($titleLine->array_options)) $titleLine->fetch_optionals();

                $TCoef = TNomenclatureCoef::loadCoef($PDOdb);

                foreach($TCoef as $coef) {
                    $titleLine->array_options['options_'.$coef->code_type] = GETPOST($coef->code_type, 'none');
                }
                $titleLine->insertExtraFields();
            }
        }
    }

	function formObjectOptions($parameters, &$object, &$action, $hookmanager)
	{
		global $langs,$conf,$form;
		$TContext = explode(':', $parameters['context']);

		if (in_array('propalcard', $TContext) || in_array('ordercard', $TContext))
		{

			if($action == 'addline') $object->fetch($object->id); // Reload to get new records
			// Ensure third party is loaded
			if ($object->socid && empty($object->thirdparty)) $object->fetch_thirdparty();

			if (count($object->lines) > 0)
			{
			?>
				<script type="text/javascript" src="<?php echo dol_buildpath('/nomenclature/js/nomenclature.js.php',1); ?>"></script>
				<script type="text/javascript">
				$(document).ready(function() {

					var lineColDescriptionPos = <?php echo (! empty($conf->global->MAIN_VIEW_LINE_NUMBER) ? 2 : 1); ?>;
					var td;
					<?php

					$picto = img_picto($langs->trans('Nomenclature'),'object_list');

					foreach($object->lines as &$line)
					{
						if ($line->product_type == 9) continue; //Filtre sur les lignes de subtotal

						if($line->fk_product>0 || !empty($conf->global->NOMENCLATURE_ALLOW_FREELINE))
						{
							$lineid = empty($line->id) ? $line->rowid : $line->id;
							$showLineNomenclatureParams = $lineid . ', ' . $line->qty . ', ' . (int) $line->fk_product . ', \\\''.$object->element.'\\\', '.$object->id;
							?>
							td = $('#row-<?php echo $lineid; ?> td.linecoldescription');

							if(td.length === 0) td = $('#row-<?php echo $lineid; ?> td:nth-child('+lineColDescriptionPos+')');

							td.append('<a href="javascript:showLineNomenclature(<?php echo $showLineNomenclatureParams; ?>)"><?php echo $picto; ?></a>');
							<?php
						}
					}
					?>
				});
				</script>
				<style type="text/css">
					.ui-autocomplete {
						z-index: 150;
					}
				</style>


				<script type="text/javascript">
					$(document).ready(function() {
						$("#fournprice_predef").ready(function(){$("#idprod").change(function() {
							var fk_product = $(this).val();
							var data = {"action": "idprod_change", "fk_product": fk_product};
							 ajax_call(data);


						})});
						function ajax_call(datas)
						{
							$.ajax({
								url: "<?php echo dol_buildpath('/nomenclature/prod_ajax.php', 1) ; ?>",
								type: "POST",
								dataType: "json",
								data: datas,

							}).done(function(data){
								if(data.result){
										 $("#fournprice_predef").hide();
										 $(".liste_titre_add td:contains('Prix de revient')").hide();

									} else {
										$("#fournprice_predef").show();
										$(".liste_titre_add td:contains('Prix de revient')").show();
									}
							}).fail(function(){
								$.jnotify('AjaxError',"error");
							});
						}
					});
				</script>
				<?php

			}

		}
		else if (in_array('stockproductcard', $TContext) && !empty($conf->global->NOMENCLATURE_ALLOW_MVT_STOCK_FROM_NOMEN))
		{
			if (!defined('INC_FROM_DOLIBARR')) define('INC_FROM_DOLIBARR', 1);

			dol_include_once('/nomenclature/config.php');
			dol_include_once('/nomenclature/class/nomenclature.class.php');

			$PDOdb = new TPDOdb;
			$nomenclature = new TNomenclature;
			$nomenclature->loadByObjectId($PDOdb, $object->id, 'product');

			$qty_theo_nomenclature = $nomenclature->getQtyManufacturable();

			$this->resprints = '<tr class="nomenclature_stock_theorique">';
			$this->resprints.= '<td>'.$form->textwithpicto($langs->trans('NomenclatureStockTheorique'), $langs->trans('NomenclatureStockTheoriqueHelp')).'</td>';
			$this->resprints.= '<td>'.price2num($object->stock_theorique+$qty_theo_nomenclature, 'MS').'</td>';
			$this->resprints.= '</tr>';
		}

		return 0; // or return 1 to replace standard code
	}

	function printObjectLine($parameters, &$object, &$action, $hookmanager) {
	    global $conf, $langs;

        $TContext = explode(':', $parameters['context']);
        $line = &$parameters['line'];

        if(in_array('propalcard', $TContext) || in_array('ordercard', $TContext)) {
            if(! $conf->subtotal->enabled) return 0;    // Inutile de faire quoi que ce soit vu qu'on a besoin d'un titre...
            dol_include_once('/nomenclature/class/nomenclature.class.php');
            ?>
            <script type="text/javascript">
                $(document).ready(function () {
                    let icon = '<i class="fa fa-line-chart" aria-hidden="true" title="<?php echo $langs->trans('UpdateTitleCoeff'); ?>" style="cursor: pointer;" data-lineid="<?php echo $line->id; ?>"></i>';
                    let tr = $('#tablelines tr[rel=subtotal][data-issubtotal=title][data-id=<?php echo $line->id; ?>]');
                    $(tr).children('td.linecoledit').prepend(icon + '&nbsp;');
                });
            </script>
            <?php
        }
        elseif (in_array('listof', $TContext))
        {
            if (!defined('INC_FROM_DOLIBARR')) define('INC_FROM_DOLIBARR', 1);
            dol_include_once('nomenclature/config.php');
            dol_include_once('nomenclature/class/nomenclature.class.php');

            if (is_object($parameters['commande']))
            {
                $PDOdb = new TPDOdb;
                $commande = $parameters['commande'];

                $n = new TNomenclature;
                $n->loadByObjectId($PDOdb, $object->fk_commandedet, 'commande', true, $object->rowid, 1, $commande->id); // si pas de fk_nomenclature, alors on provient d'un document, donc $qty_ref tjr passé en param

                if ($n->getId() > 0 && $n->non_secable)
                {
                    $langs->load('nomenclature@nomenclature');

                    $qty_to_make = 0;
                    while ($object->qteCommandee > $qty_to_make)
                    {
                        $qty_to_make+= $n->qty_reference;
                    }

                    $object->qteCommandee = $qty_to_make;

                    print '
                        <style type="text/css">
                            #formMakeOk .outline-error {
                                outline: 1px solid red;
                            }
                        </style>
                        <script type="text/javascript">
                            $(function() {
                                var nomenEl = document.getElementById("TQuantites['.$object->fk_commandedet.']");
                                nomenEl.dataset.step = '.$n->qty_reference.';
                                $(nomenEl).after("'.dol_escape_js(img_picto($langs->transnoentities('NomenclatureWarningSeuilNonSecableHelp', $n->qty_reference), 'help')).'");
//
                                $(nomenEl).keyup(function(ev) {
                                    console.log("Trigger keyup from nomenclature");
                                    let value = parseFloat(this.value.replace(",", "."));
                                    let multiple = value / this.dataset.step;

                                    if (multiple === parseInt(multiple)) {
                                        $(this).removeClass("outline-error");
                                        this.dataset.calculNextValue = 0;
                                    }
                                    else {
                                        $(this).addClass("outline-error");
                                        this.dataset.calculNextValue = 1;
                                    }
                                });

                                $(nomenEl).blur(function(ev) {
                                    console.log("Trigger blur from nomenclature");
                                    if (this.dataset.calculNextValue == 1) {
                                        let value = parseFloat(this.value.replace(",", "."));
                                        let step = parseFloat(this.dataset.step);
                                        let newValue = step;
                                        if (newValue > 0) {
                                            while (newValue < value) newValue+= step;
                                            this.value = newValue;
                                            $(this).removeClass("outline-error");
                                        }
                                    }
                                });
                            });
                        </script>
                    ';
                }
            }
        }
    }

    function addMoreActionsButtons($parameters, &$object, &$action, $hookmanager) {
	    global $conf, $langs;

        $TContext = explode(':', $parameters['context']);

        if(in_array('propalcard', $TContext) || in_array('ordercard', $TContext)) {
            if(! $conf->subtotal->enabled) return 0;    // Inutile de faire quoi que ce soit vu qu'on a besoin d'un titre...
            dol_include_once('/nomenclature/class/nomenclature.class.php');

            $PDOdb = new TPDOdb;

            $TCoeff = TNomenclatureCoefObject::loadCoefObject($PDOdb, $object, $object->element);
            ?>
            <script type="text/javascript">
                $(document).ready(function() {
                    function buildDialog(fk_line) {
                        let element = '<?php echo $object->element; ?>';


                        let dialog = $('div#nomenclatureUpdateCoeff');
                        $(dialog).children().remove();
                        $(dialog).append('<form id="updateCoeff" method="POST" action="<?php echo $_SERVER['PHP_SELF'].'?id='.$object->id; ?>">');
                        $('#updateCoeff').append('<input type="hidden" name="action" value="nomenclatureUpdateCoeff" />');
                        $('#updateCoeff').append('<input type="hidden" name="fk_line" value="'+fk_line+'" />');
                        $('#updateCoeff').append('<input type="hidden" name="element" value="'+element+'" />');
                        $('#updateCoeff').append('<table class="noborder">');

                        $.ajax({
                            url: '<?php echo dol_buildpath('/nomenclature/script/interface.php', 1); ?>',
                            data: {
                                json: 1,
                                get: 'coefs',
                                fk_line: fk_line,
                                element: element
                            },
                            dataType: 'json',
                            type: 'POST'
                        }).done(function(data) {
                            if(data !== undefined && ! Array.isArray(data)) {
                                // Custom values already applied from line fk_line
                                let out = '';
                                for (let code_type in data) {
                                    out += '<tr>';
                                    out += '<td>'+data[code_type]['label']+'</td>';
                                    out += '<td><input type="text" name="'+code_type+'" value="'+data[code_type]['value']+'" size="10" /></td>';
                                    out += '</tr>';
                                }
                                $(dialog).find('table').append(out);
                            }
                            else {
                                // Default values from object
                                <?php
                                $out = '';
                                foreach($TCoeff as $code => $coeff) {
                                    $out .= '<tr>';
                                    $out .= '<td>'.$coeff->label.'</td>';
                                    $out .= '<td><input type="text" name="'.$code.'" value="'.$coeff->tx.'" size="10" /></td>';
                                    $out .= '</tr>';
                                }
                                print "$(dialog).find('table').append('".addslashes($out)."');";
                                ?>
                            }
                        });

                        $(dialog).dialog({
                            modal: true
                            ,title: '<?php echo $langs->trans('CoefList'); ?>'
                            ,minWidth: 400
                            ,minHeight: 200
                            ,buttons: [
                                { text: "<?php echo $langs->trans('Update'); ?>", click: function() { $(this).find('form#updateCoeff').submit(); $(this).dialog("close"); } }
                                , { text: "<?php echo $langs->trans('Cancel'); ?>", click: function() { $(this).dialog("close"); } }
                            ]
                        });
                    }

                    $('#tablelines td.linecoledit i.fa-line-chart').on('click', function() {
                        let fk_line = $(this).data('lineid');
                        buildDialog(fk_line);
                    });
                });
            </script>
            <?php
        }
    }

    function printCommonFooter($parameters, &$object, &$action, $hookmanager) {

        $TContext = explode(':', $parameters['context']);
        if (in_array('ofcard', $TContext))
        {
            if (!defined('INC_FROM_DOLIBARR')) define('INC_FROM_DOLIBARR', 1);
            dol_include_once('nomenclature/config.php');
            dol_include_once('nomenclature/class/nomenclature.class.php');

                $PDOdb = new TPDOdb;

                $n = new TNomenclature;
                $n->loadByObjectId($PDOdb, GETPOST('fk_product', 'int'), 'product', true); // si pas de fk_nomenclature, alors on provient d'un document, donc $qty_ref tjr passé en param

                if ($n->getId() > 0 && $n->non_secable)
                {
                    print '
                        <style type="text/css">
                            #formOF0 .outline-error {
                                outline: 1px solid red;
                            }
                        </style>
                        <script type="text/javascript">
                            $(function() {
                                var nomenEl = document.getElementById("quantity_to_create");
                                nomenEl.value = '.$n->qty_reference.';
                                nomenEl.dataset.step = '.$n->qty_reference.';

                                $(nomenEl).keyup(function(ev) {
                                    console.log("Trigger keyup from nomenclature");
                                    let value = parseFloat(this.value.replace(",", "."));
                                    let multiple = value / this.dataset.step;

                                    if (multiple === parseInt(multiple)) {
                                        $(this).removeClass("outline-error");
                                        this.dataset.calculNextValue = 0;
                                    }
                                    else {
                                        $(this).addClass("outline-error");
                                        this.dataset.calculNextValue = 1;
                                    }
                                });

                                $(nomenEl).blur(function(ev) {
                                    console.log("Trigger blur from nomenclature");
                                    if (this.dataset.calculNextValue == 1) {
                                        let value = parseFloat(this.value.replace(",", "."));
                                        let step = parseFloat(this.dataset.step);
                                        let newValue = step;
                                        if (newValue > 0) {
                                            while (newValue < value) newValue+= step;
                                            this.value = newValue;
                                            $(this).removeClass("outline-error");
                                        }
                                    }
                                });
                            });
                        </script>
                    ';

                }


        }


	    print '<div id="nomenclatureUpdateCoeff" style="display: none;">';

	    print '</div>';
    }

    function getForecastTHM($parameters, &$object, &$action, $hookmanager) {
        global $conf, $langs, $db;

        dol_include_once('/nomenclature/class/nomenclature.class.php');
        $PDOdb = new TPDOdb;
        $TContext = explode(':', $parameters['context']);

        if(in_array('projectOverview', $TContext) && ! empty($conf->global->DOC2PROJECT_USE_NOMENCLATURE_AND_WORKSTATION)) {
            $task = $parameters['task'];
            $Tab = explode('-', $task->ref);
            $fk_nomenclatureDet = substr($Tab[0], strlen($conf->global->DOC2PROJECT_TASK_REF_PREFIX));

            $nd = new TNomenclatureDet;
            $nd->load($PDOdb, $fk_nomenclatureDet);

            return $nd->buying_price;
        }
    }


	/**
	 * Overloading the completeListOfReferent function : replacing the parent's function with the one below
	 *
	 * @param   array()         $parameters     Hook metadatas (context, etc...)
	 * @param   CommonObject    $object        The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param   string          $action        Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
    function completeListOfReferent($parameters, &$object, &$action, $hookmanager) {
        global $conf, $langs,$user;

        $TContext = explode(':', $parameters['context']);

        if(in_array('projectOverview', $TContext) && !empty($conf->global->NOMENCLATURE_FEEDBACK_INTO_PROJECT_OVERVIEW)) {

			$langs->load('nomenclature@nomenclature');

			$listofreferent = array(
				'stock_feedback'=>array(
					'name'=>"ProjectfeedbackResume",
					'title'=>"ProjectfeedbackResumeHistory",
					'class'=>'MouvementStock',
					'margin'=>'minus',
					'table'=>'stock_mouvement',
					'datefieldname'=>'datem',
					'disableamount'=>1,
					'test'=>($conf->stock->enabled && $user->rights->stock->mouvement->lire && $conf->global->NOMENCLATURE_FEEDBACK_INTO_PROJECT_OVERVIEW)
				)
			);

			$this->results = $listofreferent;
            return 1;
        }
    }


	/**
	 * Overloading the printOverviewProfit function : replacing the parent's function with the one below
	 * HOOK ajouté après le 14/12/2020 ce hook est présent à partir de la version 13 de Dolibarr
	 *
	 * @param   array()         $parameters     Hook metadatas (context, etc...)
	 * @param   CommonObject    $object        The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param   string          $action        Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	function printOverviewProfit(&$parameters, &$object, &$action, $hookmanager) {
		global $conf, $langs, $db;

		$TContext = explode(':', $parameters['context']);

		if(in_array('projectOverview', $TContext) && !empty($conf->global->NOMENCLATURE_FEEDBACK_INTO_PROJECT_OVERVIEW)) {


			$name = $langs->trans($parameters['value']['name']);

			$datefieldname = $parameters['value']['datefieldname'];
			$type = $parameters['key'];
			$dates = $parameters['dates'];
			$datee = $parameters['datee'];

			$parameters['total_revenue_ht'];
			$parameters['balance_ht'];
			$parameters['balance_ttc'];

			if ($type == 'stock_feedback' && !empty($parameters['value']['test']))
			{

				if(!empty($conf->global->NOMENCLATURE_FEEDBACK_USE_STOCK)){
					// Dans le cas ou les affectations/retours de chantier utilise les mouvement de stock alors le calcul se base sur les mouvements de stock


					// Query on stock movement
					$sql = 'SELECT COUNT(DISTINCT sm.fk_product) nbMovement, SUM(sm.value) as qty';

					// Par defaut on se base sur le PMP actuel du produit
					$stockPriceCol = 'p.pmp';
					$ProjectfeedbackResumeStockCostHTHelp = $langs->trans('ProjectfeedbackResumeStockCostHTHelp_pmp');
					if(!empty($conf->global->NOMENCLATURE_FEEDBACK_COST_BASED)) {
						if($conf->global->NOMENCLATURE_FEEDBACK_COST_BASED == 'cost_price') {
							// On se base sur le prix de revient actuel du produit
							$stockPriceCol = 'p.cost_price';
							$ProjectfeedbackResumeStockCostHTHelp = $langs->trans('ProjectfeedbackResumeStockCostHTHelp_cost_price');
						}
						elseif($conf->global->NOMENCLATURE_FEEDBACK_COST_BASED == 'stock_price') {
							// On se base sur le prix du mouvement de stock
							$stockPriceCol = 'sm.price';
							$ProjectfeedbackResumeStockCostHTHelp = $langs->trans('ProjectfeedbackResumeStockCostHTHelp');
						}
					}

					// Modification du sign du pmp en fonction du type de mouvement
					// stock movement type  2=output (stock decrease), 3=input (stock increase)
					$sql.= ',  SUM(CASE WHEN sm.type_mouvement = 2 THEN -'.$stockPriceCol.' * sm.value ELSE '.$stockPriceCol.' * sm.value END) as sumPrice  ';
					$sql.= ',  SUM(CASE WHEN sm.type_mouvement = 2 THEN -'.$stockPriceCol.' * sm.value * (1 + p.tva_tx / 100) ELSE '.$stockPriceCol.' * sm.value * (1 + p.tva_tx / 100) END) as sumPriceVAT  '; // j'ai pas mieux que la TVA du produit pour l'instant

					$sql.= ' FROM ' . MAIN_DB_PREFIX . 'stock_mouvement sm ';
					$sql.= ' JOIN ' . MAIN_DB_PREFIX . 'entrepot e ON (sm.fk_entrepot = e.rowid) ';
					$sql.= ' JOIN ' . MAIN_DB_PREFIX . 'product p ON (sm.fk_product = p.rowid) ';

					$sql.= 'WHERE ';
					$sql.= ' e.entity IN ('.getEntity('stock').') ';
					$sql.= ' AND sm.type_mouvement IN (2,3) ';
					$sql.= ' AND sm.fk_projet = '.intval($object->id).' ';

					if (empty($datefieldname) && !empty($this->table_element_date)) $datefieldname = $this->table_element_date;

					if ($dates > 0 ){
						$sql .= " AND (sm.datem >= '".$db->idate($dates)."' OR sm.datem IS NULL)";
					}

					if ($datee > 0){
						$sql .= " AND (sm.datem <= '".$db->idate($datee)."' OR sm.datem IS NULL)";
					}

				}
				else{
					// query based stock feedback table

					// Par defaut on se base sur le PMP actuel du produit
					$stockPriceCol = 'p.pmp';
					$ProjectfeedbackResumeStockCostHTHelp = $langs->trans('ProjectfeedbackResumeStockCostHTHelp_pmp');
					if(!empty($conf->global->NOMENCLATURE_FEEDBACK_COST_BASED)) {
						if($conf->global->NOMENCLATURE_FEEDBACK_COST_BASED == 'cost_price') {
							// On se base sur le prix de revient actuel du produit
							$stockPriceCol = 'p.cost_price';
							$ProjectfeedbackResumeStockCostHTHelp = $langs->trans('ProjectfeedbackResumeStockCostHTHelp_cost_price');
						}
					}


					$TAcceptedType = array('commande', 'propal');
					$object_source_type=in_array($conf->global->NOMENCLATURE_FEEDBACK_OBJECT,$TAcceptedType)?$conf->global->NOMENCLATURE_FEEDBACK_OBJECT:'commande';

					$sql = 'SELECT COUNT(DISTINCT f.fk_product) nbMovement';
					$sql.= ', SUM('.$stockPriceCol.' * f.stockAllowed) as sumPrice ';
					$sql.= ', SUM('.$stockPriceCol.' * f.stockAllowed * (1 + p.tva_tx / 100) ) as sumPriceVAT '; // j'ai pas mieux que la TVA du produit pour l'instant
					$sql.= ' FROM ' . MAIN_DB_PREFIX . $object_source_type.' s ';
					$sql.= ' JOIN ' . MAIN_DB_PREFIX . 'nomenclature_feedback f ON (s.rowid = f.fk_origin AND f.origin = "'.$db->escape($object_source_type).'") ';
					$sql.= ' JOIN ' . MAIN_DB_PREFIX . 'product p ON (p.rowid = f.fk_product)';
					$sql.= ' WHERE s.fk_projet = '.intval($object->id);

					// En commentaire car pour cette table il n'y a pas de date de "mouvement" il s'agit d'une affectation globale
//					if ($dates > 0 ){
//						$sql .= " AND (f.date_maj >= '".$db->idate($dates)."' OR f.date_maj IS NULL)";
//					}
//
//					if ($datee > 0){
//						$sql .= " AND (f.date_maj <= '".$db->idate($datee)."' OR f.date_maj IS NULL)";
//					}

				}

				$obj = $db->getRow($sql);
				if($obj){

					// Mise à jour de la balance du projet
					$parameters['balance_ht']  -= $obj->sumPrice;
					$parameters['balance_ttc'] -= $obj->sumPriceVAT;

					// Display line
					$this->resprints = '<tr class="oddeven">';

					$tabHistoryUrl = dol_buildpath('/nomenclature/tab_project_feedback_history.php', 1).'?id='.$object->id;

					// Element label
					$this->resprints.= '<td class="left">'.$name;
					if(!empty($conf->global->NOMENCLATURE_FEEDBACK_USE_STOCK)) {
						$this->resprints .= ' <small><a href="' . $tabHistoryUrl . '" >(' . $langs->trans('ShowFeedBackHistoryDetails') . ')</a></small>';
					}
					$this->resprints.= '</td>';

					// Nb
					$this->resprints.= '<td class="right"><span class="classfortooltip" title="'.$langs->trans('ProjectfeedbackResumeNbMovementHelp').'" >'.$obj->nbMovement.'</span></td>';


					// Amount HT
					$this->resprints.= '<td class="right">';
					$this->resprints.= '<span class="classfortooltip" title="'.dol_htmlentities($ProjectfeedbackResumeStockCostHTHelp, ENT_QUOTES).'" >'.price($obj->sumPrice).'</span>';
					$this->resprints.= '</td>';

					// Amount TTC
					$this->resprints.= '<td class="right">';
					$this->resprints.= '<span class="classfortooltip" title="'.dol_htmlentities($ProjectfeedbackResumeStockCostHTHelp, ENT_QUOTES).'" >'.price(!empty($obj->sumPriceVAT)?$obj->sumPriceVAT:$obj->sumPrice).'</span>';
					$this->resprints.= '</td>';

					$this->resprints.= '</tr>';
				}
				else{
					$this->resprints = '<tr class="oddeven">';

					$this->resprints.= '<td class="left" colspan="6">'.$langs->trans('DataBaseQueryError').'</td>'; // $db->error().'<br>'.$sql.

					$this->resprints.= '</tr>';
				}

				return 1;
			}
		}
	}


	/**
	 * Overloading the printOverviewProfit function : replacing the parent's function with the one below
	 * HOOK ajouté après le 14/12/2020 ce hook est présent à partir de la version 13 de Dolibarr
	 * @param   array()         $parameters     Hook metadatas (context, etc...)
	 * @param   CommonObject    $object        The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param   string          $action        Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	function getElementList(&$parameters, &$object, &$action, $hookmanager) {
		global $conf, $langs, $db;

		$TContext = explode(':', $parameters['context']);

		if(in_array('projectdao', $TContext)) {


			$name = $langs->trans($parameters['value']['name']);

			$datefieldname = $parameters['datefieldname'];
			$type = $parameters['type'];
			$dates = $parameters['dates'];
			$datee = $parameters['datee'];
			$fk_projet = $parameters['fk_projet'];

			if ($type == 'stock_feedback')
			{
				/*
				 * Cette partie est nomalement useless car usurpé avec les hook printOverviewProfit et printOverviewDetail
				 * Je la carde au cas ou getElementList serai utilisé en dehors de project overview pour éviter une erreur SQL
				 */

				$sql = 'SELECT sm.rowid';
				$sql.= ' FROM ' . MAIN_DB_PREFIX . 'stock_mouvement sm ';
				$sql.= ' JOIN ' . MAIN_DB_PREFIX . 'entrepot e ON (sm.fk_entrepot = e.rowid) ';
				$sql.= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'product p ON (sm.fk_product = p.rowid) ';

				$sql.= 'WHERE ';
				$sql.= ' e.entity IN ('.getEntity('stock').') ';
				$sql.= ' AND sm.type_mouvement IN (2,3) ';
				$sql.= ' AND sm.fk_projet IN ('.$fk_projet.') ';

				if (empty($datefieldname) && !empty($this->table_element_date)) $datefieldname = $this->table_element_date;

				if ($dates > 0 ){
					$sql .= " AND (sm.datem >= '".$db->idate($dates)."' OR sm.datem IS NULL)";
				}

				if ($datee > 0){
					$sql .= " AND (sm.datem <= '".$db->idate($datee)."' OR sm.datem IS NULL)";
				}

				$this->resprints = $sql;
				return 1;
			}
		}
	}

	/**
	 * Overloading the printOverviewDetail function : replacing the parent's function with the one below
	 * HOOK ajouté après le 14/12/2020 ce hook est présent à partir de la version 13 de Dolibarr
	 *
	 * @param   array()         $parameters     Hook metadatas (context, etc...)
	 * @param   CommonObject    $object        The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param   string          $action        Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	function printOverviewDetail(&$parameters, &$object, &$action, $hookmanager) {
		global $conf, $langs,$user;

		$TContext = explode(':', $parameters['context']);

		if(in_array('projectOverview', $TContext)) {
			if ($parameters['key'] == 'stock_feedback' && !empty($parameters['value']['test']))
			{
				// skip details because there is already a tab for that
				return 1;
			}
		}
	}




}
