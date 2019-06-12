<?php

require '../config.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
dol_include_once('/nomenclature/class/nomenclature.class.php');

$PDOdb = new TPDOdb;
$TCoeff = TNomenclatureCoef::loadCoef($PDOdb);

foreach($TCoeff as $coeff) {
    $sql = 'SELECT rowid';
    $sql.= ' FROM '.MAIN_DB_PREFIX.'extrafields';
    $sql.= " WHERE name = '".$db->escape($coeff->code_type)."'";
    $sql.= " AND elementtype IN ('propaldet', 'commandedet')";

    $resql = $db->query($sql);

    if($resql) {
        $nbRow = $db->num_rows($resql);

        // On les crée seulement s'ils n'existent pas déjà
        if(empty($nbRow)) {
            $e = new ExtraFields($db);
            $e->addExtraField($coeff->code_type, $coeff->label, 'price', 100, '24,8', 'propaldet', 0, 0, '', '', 0, '', 0);
            $e->addExtraField($coeff->code_type, $coeff->label, 'price', 100, '24,8', 'commandedet', 0, 0, '', '', 0, '', 0);
        }
    }
    else {
        dol_print_error($db);
        exit;
    }

    $db->free($resql);
}
