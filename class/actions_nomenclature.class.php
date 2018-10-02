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
	function doActions($parameters, &$object, &$action, $hookmanager)
	{

	}

	function formObjectOptions($parameters, &$object, &$action, $hookmanager)
	{
		global $langs,$conf,$form;
		$TContext = explode(':', $parameters['context']);		
		
		if (in_array('propalcard', $TContext) || in_array('ordercard', $TContext))
		{
			if($object->brouillon == 1 && count($object->lines) > 0)
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

}
