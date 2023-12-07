<?php
define('INC_FROM_CRON_SCRIPT',true);
if (!defined("NOCSRFCHECK")) define("NOSCRFCHECK", 1);
if (!defined('NOTOKENRENEWAL')) {
	define('NOTOKENRENEWAL', 1);
}
require('../config.php');

$langs->load('nomenclature@nomenclature');

// Define javascript type
if(function_exists('top_httphead')) top_httphead('text/javascript; charset=UTF-8');
// Important: Following code is to avoid page request by browser and PHP CPU at each Dolibarr page access.
if (empty($dolibarr_nocache)) {
	header('Cache-Control: max-age=10800, public, must-revalidate');
} else {
	header('Cache-Control: no-cache');
}
?>
var ButtonWhoSubmit;

function showLineNomenclature(fk_line, qty, fk_product, object_type, fk_origin) {

       var bindItem = function () {

           $div.find('[type="submit"]').click(function(e){
                    ButtonWhoSubmit = $(this).attr('name');
               });

               $div.find('a.tojs').each(function(){
                    $a = $(this);
                    var url = $(this).attr('href');
                    $(this).attr('href','javascript:;');
                    $(this).unbind().click(function() {
                       $.ajax({
                           url: url
						   ,data: { disableAnchorRedirection : true }
                       }).done(function(data) {
                           $a.closest('.ui-dialog').effect( "shake", { direction : 'up', times : 1 } );
                            $div.html(data);
                            bindItem();
                       });

                    });

               });

               $div.find('form').submit(function() {
                   var data = $(this).serialize();
                   data+='&'+ButtonWhoSubmit+'=1&disableAnchorRedirection=1';
                   /*console.log(data);*/
                   $.post($(this).attr('action'), data, function() {
                        $div.dialog('option','title',"<?php echo $langs->transnoentities('NomenclatureLineSaved'); ?>");
                   }).done(function(data) {
                       $div.closest('.ui-dialog').effect( "shake", { direction : 'up', times : 1 } );
                       $div.html(data);
                       bindItem();

                       if (ButtonWhoSubmit == 'apply_nomenclature_price')
                       {
                       		var url = false;
                       		switch(object_type) {
							    case 'propal':
							    <?php if((float) DOL_VERSION >= 4.0){ ?>
									url = "<?php echo dol_buildpath('/comm/propal/card.php?id=', 1); ?>"+fk_origin;
							    <?php }else{ ?>
									url = "<?php echo dol_buildpath('/comm/propal.php?id=', 1); ?>"+fk_origin;
							    <?php } ?>
							        break;
								case 'commande':
									url = "<?php echo dol_buildpath('/commande/card.php?id=', 1); ?>"+fk_origin;
									break;
								case 'facture':
									url = "<?php echo dol_buildpath('/compta/facture/card.php?id=', 1); ?>"+fk_origin;
									break;
							}

							if (url)
							{
								$.ajax({
									url: url
									,data: { disableAnchorRedirection : true }
									,success: function(html) {
										$('#id-right > .fiche').replaceWith($(html).find('#id-right > .fiche'));
										let hash = "#row-" + fk_line;
										$('html, body').animate({
											scrollTop: $(hash).offset().top
										}, 800, function(){
											// Add hash (#) to URL when done scrolling (default click behavior)
											window.location.hash = hash;
										});
<?php  	                                if(getDolGlobalInt('NOMENCLATURE_CLOSE_ON_APPLY_NOMENCLATURE_PRICE'))
										{

										    print "\n".'$("#dialog-nomenclature").dialog(\'close\'); '."\n";
										}
?>
									}
								});
							}

                       }
                   });

                    return false;
               });


       }

       var openDialog = function(data) {

               $("#dialog-nomenclature").remove();

               $('body').append('<div id="dialog-nomenclature"></div>');

               $div = $("#dialog-nomenclature");
               $div.html(data);
               bindItem();

               $("#dialog-nomenclature").dialog({
                  resizable: true,
                  modal: true,
                  dialogClass: "dialogSouldBeZindexed",
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
                 fk_object: fk_line
                , qty_ref: qty
                , fk_product: fk_product
                , object_type: object_type
                , fk_origin: fk_origin
                , json : 1
			   , disableAnchorRedirection : true
           }
           ,dataType:'html'
       }).done(function(data){

           openDialog(data);

       }) ;

}
