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
            
            $n=new TNomenclatureLine;
            $n->load($PDOdb,(int)GETPOST('lineid'), (float)GETPOST('qty'));
            __out((array)$n);
            
            break;
        
    }
    
}
function _put(&$PDOdb, $case) {
    
    switch ($case) {
        case 'nomenclature-line':
            
            break;
        
    }
    
}
