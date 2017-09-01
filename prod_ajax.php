<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

require 'config.php';
dol_include_once('/nomenclature/class/nomenclature.class.php');

$PDOdb = new TPDOdb;
if(GETPOST('action'))
	{
		$action = GETPOST('action');
		
		
		if($action == 'idprod_change')
		{
			$fk_product= (int)GETPOST('fk_product');
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