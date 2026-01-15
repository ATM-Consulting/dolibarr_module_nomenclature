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

require '../config.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
dol_include_once('/nomenclature/class/nomenclature.class.php');

$PDOdb = new TPDOdb;
$TCoeff = TNomenclatureCoef::loadCoef($PDOdb);
global $db;
foreach($TCoeff as $coeff) {
    $sql = 'SELECT rowid';
    $sql.= ' FROM '.$db->prefix().'extrafields';
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
