var options = {
		placeholderClass: 'placeholderClass',
		hintClass: 'hintClass',
		ignoreClass: 'clicable',
		currElClass: 'currElemClass',
		
		insertZone: 30,
		onDragStart: function(e, el)
		{
			null;
		},
		isAllowed: function( cEl, hint, target ) {
			if( cEl.hasClass('lineObject') && hint.parent().attr('id') == 'speednomenclature' ) {
				hint.css('background-color', '#9999ff');
				return true;
			}
			else if( target.hasClass('lineObject') || target.hasClass('workstation') /*|| target.closest('li').length==0*/ ) {
				hint.css('background-color', '#ff9999');
				return false;
			}
			else {
				hint.css('background-color', '#99ff99');
				return true;
			}
		},
		onChange:function(cEl) {
		 	
		}
	};

$(document).ready(function() {
	//http://camohub.github.io/jquery-sortable-lists/
	$('#speednomenclature a').addClass('clicable');
	$('#speednomenclature').sortableLists( options );
	
	
	$('#speednomenclature').mousemove(function (e) {
        $('div#addto').offset({ top: e.pageY - 50 });
    });
	
	$('#speednomenclature li').mouseenter(function(e) {
		$('#speednomenclature li').removeClass('selectedElement');
		$(this).addClass('selectedElement');
	});
	
	$('input[name=AddProductNomenclature]').click(function() {
		
		label = $('#fk_product option:selected').text();
		if(label == '') label = $('#search_fk_product').val();
		addProduct($('#addto #fk_product').val(),label);
	});
	
	
	$('input[name=SaveAll]').click(function() {
		var THierarchie = $('#speednomenclature').sortableListsToHierarchy();
		
		THierarchie = parseHierarchie(THierarchie);
		console.log(THierarchie);
		$.ajax({
			url:"script/interface.php"
			,data : {
				put:'nomenclatures'
				,THierarchie:THierarchie
			}
		});
	});
	
});

function parseHierarchie(THierarchie) {
	
	for(x in THierarchie) {
		
		$li = $('li#'+THierarchie[x].id);
		
		//if($li.attr('line-type') == 'nomenclature' || $li.attr('line-type') == 'workstation') {
		THierarchie[x].fk_object = $li.attr('fk_object');
		THierarchie[x].object_type = $li.attr('object_type');
		
		THierarchie[x].fk_product = $li.attr('fk_product');  
		THierarchie[x].fk_original_nomenclature = $li.closest('ul').attr('fk_original_nomenclature');
		THierarchie[x].fk_nomenclature = $li.closest('ul').attr('fk_nomenclature');
		//THierarchie[x].k = $li.attr('k');
		//}
		
		if(THierarchie[x].children && THierarchie[x].children.length>0) {
			THierarchie[x].children = parseHierarchie(THierarchie[x].children);
		}	
		
	}
	
	return THierarchie;
}

function addProduct(fk_product,label) {
	console.log('addProduct',fk_product,label);
	if($('li.selectedElement>ul').length == 0)$('li.selectedElement').append('<ul />');
	$to = $('li.selectedElement>ul');
	
	if(label == '')label='...';
	$li = $('<li id="new-product-'+Math.floor(Math.random()*100000)+'" object_type="product" fk_object="'+fk_product+'" class="newElement">'+label+'</li>');
	
	$to.append($li);
	
}
