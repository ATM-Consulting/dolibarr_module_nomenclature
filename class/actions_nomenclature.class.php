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
                            $line->array_options['options_'.$coef->code_type] = GETPOST($coef->code_type);
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
                    $titleLine->array_options['options_'.$coef->code_type] = GETPOST($coef->code_type);
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
                $n->loadByObjectId($PDOdb, GETPOST('fk_product'), 'product', true); // si pas de fk_nomenclature, alors on provient d'un document, donc $qty_ref tjr passé en param

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

}
