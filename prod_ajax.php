<?php

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
