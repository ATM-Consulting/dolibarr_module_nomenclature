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

if (!defined('NOREQUIREMENU'))  define('NOREQUIREMENU', '1');				// If there is no need to load and show top and left menu
if (!defined('NOREQUIREHTML'))  define('NOREQUIREHTML', '1');				// If we don't need to load the html.form.class.php
if (!defined('NOCSRFCHECK'))    define('NOCSRFCHECK', '1');
if (!defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL', '1');

require 'config.php';
dol_include_once('/nomenclature/class/nomenclature.class.php');

$PDOdb = new TPDOdb;
if(GETPOST('action', 'alpha'))
	{
		$action = GETPOST('action', 'alpha');


		if($action == 'idprod_change')
		{
			$fk_product= (int)GETPOST('fk_product', 'int');
			$nomenclature = new TNomenclature();
			$nomenclature->loadByObjectId($PDOdb, $fk_product, 'product');


			if($nomenclature->iExist)
			{
				$data['result'] = 1;
			}
			else
			{
				$data['result'] = 0;
			}
		}
	}
echo json_encode($data);
