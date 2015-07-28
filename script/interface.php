<?php

    require('../config.php');
    dol_include_once('/nomenclature/class/nomenclature.class.php');
    if($conf->workstation->enabled) {
        dol_include_once('/workstation/class/workstation.class.php');
    }

    $PDOdb = new TPDOdb;

    $get = GETPOST('get');
    $put = GETPOST('put');
    
    _get($PDOdb,$get);
    _put($PDOdb,$put);
    
function _get(&$PDOdb, $case) {
    
    switch ($case) {
        case 'nomenclature-line':
            
            __out(_get_nomenclature_line());
            
            break;
        
    }
    
}
function _put(&$PDOdb, $case) {
    
    switch ($case) {
        case 'nomenclature-line':
            
            break;
        
    }
    
}
function _get_nomenclature_line() {
	
	
	
}
