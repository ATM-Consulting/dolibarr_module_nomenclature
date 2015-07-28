<?php
    
    define('INC_FROM_CRON_SCRIPT',true);
    require('../config.php');

    $langs->load('nomenclature@nomenclature');

?>
function showLineNomenclature(lineid, qty, fk_product, objecttype) {

       var openDialog = function(data) {
                
               $("#dialog-nomenclature").remove(); 
                
               $('body').append('<div id="dialog-nomenclature"></div>');
               drawDetail(data);
                
               $("#dialog-nomenclature").dialog({
                  resizable: true,
                  modal: true,
                  width:'90%',
                  title:"<?php echo $langs->trans('Nomenclature'); ?>",
                  buttons: {
                    "Delete all items": function() {
                      $( this ).dialog( "close" );
                    },
                    Cancel: function() {
                      $( this ).dialog( "close" );
                    }
                  }
                });
                
       };
       
       var drawDetail = function (nomenclature) {
           
           $div = $("#dialog-nomenclature");
           $div.empty();
           
           $div.append('<table width="100%" class="liste" rel="list-product"><tr class="liste_titre"><td class="liste_titre"><?php echo $langs->trans('Type'); ?></td><td class="liste_titre"><?php echo $langs->trans('Product'); ?></td><td class="liste_titre"><?php echo $langs->trans('Qty'); ?></td></tr></table>');
           $div.append('<table width="100%" class="liste" rel="list-ws"><tr class="liste_titre">'
                   +'<td class="liste_titre"><?php echo $langs->trans('Worstations'); ?></td>'
                   +'<td class="liste_titre"><?php echo $langs->trans('QtyPrepare'); ?></td>'
                   +'<td class="liste_titre"><?php echo $langs->trans('QtyFabrication'); ?></td>'
                   +'<td class="liste_titre"><?php echo $langs->trans('Qty'); ?></td>'
                   +'<td class="liste_titre"><?php echo $langs->trans('Rank'); ?></td></tr></table>');
           
           $table = $div.find('table[rel="list-product"]');
           
           for(x in nomenclature.TNomenclatureLineDet) {
               
               l = nomenclature.TNomenclatureLineDet[x];
               
               $obj = $('<tr></tr>');
               $obj.append('<td>'+l.product_type+'</td>');
               $obj.append('<td>'+l.fk_product+'</td>');
               $obj.append('<td>'+l.qty+'</td>');
               
               $table.append($obj);
               
           }
           
           
           $table = $div.find('table[rel="list-ws"]');
           
           for(x in nomenclature.TNomenclatureLineWorkstation) {
               
               l = nomenclature.TNomenclatureLineWorkstation[x];
               
               $obj = $('<tr></tr>');
               $obj.append('<td>'+l.fk_workstation+'</td>');
               $obj.append('<td>'+l.nb_hour_prepare+'</td>');
               $obj.append('<td>'+l.nb_hour_manufacture+'</td>');
               $obj.append('<td>'+l.nb_hour+'</td>');
               $obj.append('<td>'+l.rang+'</td>');
               
               $table.append($obj);
               
           }
           
       };
                 
       $.ajax({
           url:"<?php echo dol_buildpath('/nomenclature/script/interface.php',1); ?>"
           ,data: {
                get:'nomenclature-line'
                ,lineid: lineid
                , qty: qty
                , fk_product: fk_product  
                , json : 1
           } 
           ,dataType:'json'
       }).done(function(data){
           
           openDialog(data);
           
       }) ;
       
}
