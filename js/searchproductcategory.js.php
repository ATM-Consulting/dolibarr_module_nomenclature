<?php
if (!defined('NOTOKENRENEWAL')) {
	define('NOTOKENRENEWAL', 1);
}
if (!defined("NOCSRFCHECK")) define("NOSCRFCHECK", 1);
require '../config.php';

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
var NomenclatureSpc_line_class = 'even';
$(document).ready(function() {

	$(".nomenclature-searchbycat").html('<?php echo img_picto($langs->trans('SearchByCategory'), 'object_searchproductcategory.png@nomenclature') ?>');

	$(".nomenclature-searchbycat").click(function() {
  		if($('div#popNomenclatureSearchProductByCategory').length == 0) {

    		$('body').append('<div id="popNomenclatureSearchProductByCategory" class="arboContainer" spc-role="arbo"><div class="arbo"></div></div>');
    		$( "div#popNomenclatureSearchProductByCategory" ).dialog({
    	      modal: true,
    	      autoOpen: false,
    	      title:"<?php echo $langs->transnoentities('SearchByCategory'); ?>",
    	      width:"80%",
    	      buttons: {
    	        "<?php echo $langs->trans('Cancel'); ?>": function() {
    	          $( this ).dialog( "close" );
    	        }
    	      }
    	    });

    	    initNomenclatureSearchProductByCategory("div#popNomenclatureSearchProductByCategory div.arbo");
    	}

    	$pop = $( "div#popNomenclatureSearchProductByCategory" );
    	//$pop.attr('related', $(a).attr('related'));
    	//$pop.attr('related-label', $(a).attr('related-label'));

    	$pop.dialog('open');
	});

});


function SearchNomenclatureCategorySPC(a) {

	var keyword = $(a).prev('input[name=spc_keyword]').val();
	getNomenclatureArboSPC(0, $("div#arboresenceCategoryProduct,div#popNomenclatureSearchProductByCategory div.arbo"), keyword) ;

}
function getNomenclatureArboSPC(fk_parent, container,keyword) {

	container.find('ul.tree').remove();
	container.append('<span class="loading"><?php echo img_picto('', 'working.gif') ?></span>');

	$.ajax({
		url:"<?php echo dol_buildpath('/nomenclature/script/interface.php',1) ?>"
		,data:{
			get:"categories"
			,fk_parent:fk_parent
			,keyword:keyword
		}
		,dataType:'json'
	}).done(function(data) {

		$ul = $('<ul class="tree" fk_parent="'+fk_parent+'"></ul>');

		if(data.TCategory.length == 0 && data.TProduct.length ==0) {
			//$ul.append('<li class="none '+NomenclatureSpc_line_class+'">
			<?php
			/**
                if(!empty($conf->global->SPC_DO_NOT_LOAD_PARENT_CAT)) {
					echo $langs->trans('DoASearch');
				}
				else {
					echo $langs->trans('NothingHere');
				}
             */
			?>
			//</li>');
		}
		else {
			$.each(data.TCategory,function(i,item) {
				NomenclatureSpc_line_class = (NomenclatureSpc_line_class == 'even') ? 'odd' : 'even';
				$ul.append('<li class="category '+NomenclatureSpc_line_class+'" catid="'+item.id+'"><a href="javascript:getNomenclatureArboSPC('+item.id+', $(\'li[catid='+item.id+']\') )">'+item.label+'</a></li>');
			});

			$.each(data.TProduct,function(i,item) {
				NomenclatureSpc_line_class = (NomenclatureSpc_line_class == 'even') ? 'odd' : 'even';

				var TRadioboxMultiPrice = '';
				<?php if (!empty($conf->global->PRODUIT_MULTIPRICES)) { ?>
					for (var p in item.multiprices) {
						if (item.multiprices_base_type[p] == 'TTC') var priceToUse = parseFloat(item.multiprices_ttc[p]);
						else var priceToUse = parseFloat(item.multiprices[p]);

						if (isNaN(priceToUse)) priceToUse = 0;

						var checked = false;
						if (data.default_price_level == p) checked = true;
						TRadioboxMultiPrice += '<span class="multiprice"><input '+(checked ? "checked" : "")+' class="radioSPC" type="radio" name="TProductSPCPriceToAdd['+item.id+']" value="'+priceToUse+'" data-fk-product="'+item.id+'" style="vertical-align:bottom;" /> ' + priceToUse.toFixed(2) + '</span>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
					}
				<?php } ?>

				$li = $('<li class="product '+NomenclatureSpc_line_class+'" productid="'+item.id+'"><input type="checkbox" value="1" name="TProductSPCtoAdd['+item.id+']" fk_product="'+item.id+'" class="checkSPC" /> <a class="checkIt" href="javascript:;" onclick="checkProductSPC('+item.id+')" >'+item.label+'</a> <a class="addToForm" href="javascript:;" onclick="addNomenclatureProductSPC('+item.id+',\''+item.label.replace(/\'/g, "&quot;")+'\', \''+item.ref+'\')"><?php echo img_right($langs->trans('SelectThisProduct')) ?></a> '+TRadioboxMultiPrice+' </li>');

				<?php if (!empty($conf->global->SPC_DISPLAY_DESC_OF_PRODUCT)) { ?>
					var desc = item.description.replace(/'/g, "\\'");

				<?php 	if(!empty($conf->global->PRODUCT_USE_UNITS)){ ?>
						desc = desc + "\n Unit : "+item.unit;
				<?php } ?>
					var bubble = $("<?php echo addslashes(img_help()); ?>");
					bubble.attr('title', desc);

					$li.append(bubble);
				<?php } else if (!empty($conf->global->PRODUCT_USE_UNITS)) { ?>
					var unit = "Unit : "+item.unit;
					var bubble = $("<?php echo addslashes(img_help()); ?>");
					bubble.attr('title', unit);
					$li.append(bubble);
				<?php } ?>

				$ul.append($li);
			});
		}

		container.find('span.loading').remove();
		container.append($ul);

		$('#arboresenceCategoryProduct').find('a.addToForm').remove();
		$("div#popNomenclatureSearchProductByCategory").find('input[type=checkbox], span.multiprice').remove();

		var TCheckIt = $("div#popNomenclatureSearchProductByCategory").find('a.checkIt');
		for (var j=0; j < TCheckIt.length; j++)
		{
			$(TCheckIt[j]).attr('onclick', $(TCheckIt[j]).next('a.addToForm').attr('onclick'));
		}
	});
}

function checkProductSPC(fk_product) {
	if( $('input[name="TProductSPCtoAdd['+fk_product+']"]').is(':checked') ) {
		$('input[name="TProductSPCtoAdd['+fk_product+']"]').prop('checked',false);
	}
	else {
		$('input[name="TProductSPCtoAdd['+fk_product+']"]').prop('checked',true);
	}

}

function addNomenclatureProductSPC(fk_product,label,ref) {

	$('[id^=fk_new_product_]').val(fk_product);
	$('[id^=fk_new_product_]').trigger('change');

	$pop = $( "div#popNomenclatureSearchProductByCategory" );
	$pop.dialog('close');
}

function initNomenclatureSearchProductByCategory(selector) {

	$arbo = $( selector );
	$arbo.html();
	$arbo.append('<div><input type="text" value="" name="spc_keyword" size="10" /> <a href="javascript:;" onclick="SearchNomenclatureCategorySPC(this)"><?php echo img_picto('','search'); ?></a></div>');
	$arbo.append('<ul class="tree"><?php echo img_picto('', 'working.gif') ?></ul>');

	getNomenclatureArboSPC(0, $arbo);
}
