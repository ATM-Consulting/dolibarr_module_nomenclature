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
		global $langs,$conf;
		$TContext = explode(':', $parameters['context']);		
		
		if (in_array('propalcard', $TContext) || in_array('ordercard', $TContext))
		{
		    
			if($object->brouillon == 1) {
				?>
				<script type="text/javascript" src="<?php echo dol_buildpath('/nomenclature/js/nomenclature.js.php',1); ?>"></script>
				<script type="text/javascript"> 
				$(document).ready(function() {
				    <?php
				
			  	foreach($object->lines as &$line) 
			  	{
			  		if ($line->product_type == 9) continue; //Filtre sur les lignes de subtotal
					
					if(($line->fk_product>0 && $line->product_type == 0) || ($conf->global->NOMENCLATURE_ALLOW_FREELINE)) 
					{
						$lineid = empty($line->id) ? $line->rowid : $line->id;
						
						print '$("#row-'.$lineid.' td:first").append(\'<a href="javascript:showLineNomenclature('.$lineid.','.$line->qty.','.(int) $line->fk_product.',\\\''.$object->element.'\\\', '.$object->id.')">'.img_picto($langs->trans('Nomenclature'),'object_list').'</a>\');';
					}
			  	}
				
				?> });
				</script>
				<style type="text/css">
					.ui-autocomplete {
						z-index: 150;
					}
				</style>
				
				<?php
			}
			
		}

		return 0; // or return 1 to replace standard code
	}

}