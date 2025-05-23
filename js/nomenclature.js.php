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
									url = "<?php echo dol_buildpath('/comm/propal/card.php?id=', 1); ?>"+fk_origin;
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
									}
								});
							}

                       }
                   });

                    return false;
               });


       }

	var openDialog = function (data) {

		$("#dialog-nomenclature").remove();

		$('body').append('<div id="dialog-nomenclature"></div>');

		$div = $("#dialog-nomenclature");
		$div.html(data);
		bindItem();

		$("#dialog-nomenclature").dialog({
			resizable: true,
			modal: true,
			dialogClass: "dialogSouldBeZindexed",
			title: "<?php echo $langs->trans('Nomenclature'); ?>",
			top: 0,
			width: '98%',
			height : $(window).height() - 20,
			open: function (event, ui) {
				// Positionnement du dialogue en haut de la page
				$(this).closest('.ui-dialog').css({
					'top': '10px'
				});
				// Remonter en haut de page
				$('html, body').animate({
					scrollTop: 0
				}, 0);

				setTimeout(() => {
					let targetSelect2 = $('#dialog-nomenclature [id^=fk_new_product_]');
					targetSelect2.select2('open');
					targetSelect2.on('select2:select', function (e) {
						targetSelect2.select2('focus');
					});
				}, 150);
			},
			buttons: {}
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
