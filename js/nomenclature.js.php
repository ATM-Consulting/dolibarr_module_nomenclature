<?php
    
    define('INC_FROM_CRON_SCRIPT',true);
    require('../config.php');

    $langs->load('nomenclature@nomenclature');

?>
function showLineNomenclature(fk_line, qty, fk_product, object_type) {

       var openDialog = function(data) {
                
               $("#dialog-nomenclature").remove(); 
                
               $('body').append('<div id="dialog-nomenclature"></div>');
               
               $div = $("#dialog-nomenclature");
               $div.html(data);
               
               $div.find('form').submit(function() {
	               	$.post($(this).attr('action'), $(this).serialize(), function() {
						$div.dialog('option','title',"<?php echo $langs->trans('NomenclatureLineSaved'); ?>");
					});
			
					return false;
               });
                
               $("#dialog-nomenclature").dialog({
                  resizable: true,
                  modal: true,
                  width:'90%',
                  title:"<?php echo $langs->trans('Nomenclature'); ?>",
                  buttons: {
                    
                  }
                });
                
       };
       
/* nomenclature/nomenclature.php?fk_product=2&lineid=4&object_type=commande&qty=1 */
       $.ajax({
           url:"<?php echo dol_buildpath('/nomenclature/nomenclature.php',1); ?>"
           ,data: {
                get:'nomenclature-line'
                , fk_line: fk_line
                , qty: qty
                , fk_product: fk_product  
                , object_type: object_type
                , json : 1
           } 
           ,dataType:'html'
       }).done(function(data){
           
           openDialog(data);
           
       }) ;
       
}
