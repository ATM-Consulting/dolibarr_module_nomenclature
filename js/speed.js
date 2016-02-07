var options = {
		placeholderClass: 'placeholderClass',
		hintClass: 'hintClass',
		ignoreClass: 'clicable',
		currElClass: 'currElemClass',
		
		insertZone: 100,
		onDragStart: function(e, el)
		{
			null;
		},
		isAllowed: function( cEl, hint, target ) {
			if ( 
			 	 ((cEl.attr('line-type') == 'line' || cEl.attr('line-type') == 'special') && hint.parent().attr('container-type') != 'main')
			 	|| (( cEl.attr('line-type') == 'workstation'|| cEl.attr('line-type') == 'nomenclature') && (hint.parent().attr('container-type') == 'main' || hint.closest('li[line-type]').attr('line-type') == 'workstation' ) || hint.closest('li[line-type]').attr('line-type') == 'special')
				 
			 	)
			 {
			 	hint.css('background-color', '#ff9999');
				return false;
			}
			else if( cEl.attr('line-type') == 'line' && hint.parent().attr('container-type') == 'main' ) {
			// type ligne à réordonner
				hint.css('background-color', '#9999ff');
				return true;
			}

			else {
				hint.css('background-color', '#99ff99');
				return true;
			}
		},
		onChange:function(cEl) {
		 	
		}/*,
		opener: {
			active: true,
			close: './img/add.png',
			open: './img/remove.png',
			css: {
				'display': 'inline-block', // Default value
				'float': 'left', // Default value
				'width': '18px',
				'height': '18px',
				'margin-left': '5px',
				'margin-right': '5px',
				'background-position': 'center center', // Default value
				'background-repeat': 'no-repeat' // Default value
			}
		}*/
	};

$(document).ready(function() {
	//http://camohub.github.io/jquery-sortable-lists/
	$('#speednomenclature a').addClass('clicable');
	$('#speednomenclature').sortableLists( options );
	
	
	$('#speednomenclature').mousemove(function (e) {
        $('div#addto').offset({ top: e.pageY - 50 });
    });
	
	$('#speednomenclature li').mouseenter(function(e) {
		if($(this).attr('line-type') == 'line' || $(this).attr('line-type')=='nomenclature') {
			$('#speednomenclature li').removeClass('selectedElement');
			$(this).addClass('selectedElement');	
		}
	});
	
	$('input[name=AddProductNomenclature]').click(function() {
		
		label = $('#fk_product option:selected').text();
		if(label == '') label = $('#search_fk_product').val();
		addProduct($('#addto #fk_product').val(),label);
	});
	
	$('input[name=AddWorkstation]').click(function() {
		label = $('#fk_new_workstation option:selected').text();
		addWorkstation($('#addto #fk_new_workstation').val(),label);
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
				,fk_object:fk_object
				,object_type:object_type
			}
			,method:'post'
		});
	});
	
	$('input[rel=qty]').change(function() {
		
		var qty = $(this).val();
		
		$li = $(this).closest('li');
		var fk_nomenclature = $li.closest('ul').attr('fk_nomenclature');
		
		var fk_object = $li.attr('fk_object');
		var object_type = $li.attr('object_type');
		fk_nomenclature
		$('ul[fk_nomenclature='+fk_nomenclature+']>li[fk_object='+fk_object+'][object_type='+object_type+']>div>div.qty>input[rel=qty]').val(qty).css({ 'background-color':'#00ff00' }).animate({'background-color':'#ffffff'},'slow');
		
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
		
		if($li.find('input[rel=qty]')) THierarchie[x].qty = $li.find('input[rel=qty]').val();
		if($li.find('input[rel=nb_hour_manufacture]')) THierarchie[x].nb_hour_manufacture = $li.find('input[rel=nb_hour_manufacture]').val();
		if($li.find('input[rel=nb_hour_prepare]')) THierarchie[x].nb_hour_prepare = $li.find('input[rel=nb_hour_prepare]').val();
		//THierarchie[x].k = $li.attr('k');
		//}
		
		if(THierarchie[x].children && THierarchie[x].children.length>0) {
			THierarchie[x].children = parseHierarchie(THierarchie[x].children);
		}	
		
	}
	
	return THierarchie;
}

function addWorkstation(fk_ws, label) {
	if($('li.selectedElement').length!=1) return false;
	console.log('addProduct',fk_ws,label);
	
	if($('li.selectedElement>ul').length == 0)$('li.selectedElement').append('<ul container-type="nomenclature" />');
	$to = $('li.selectedElement>ul');
	
	if(label == '')label='...';
	$li = $('<li id="new-ws-'+Math.floor(Math.random()*100000)+'" line-type="workstation" object_type="workstation" fk_object="'+fk_ws+'" class="newElement">'+label+'</li>');
	
	$to.append($li);
}

function addProduct(fk_product,label) {
	
	if($('li.selectedElement').length!=1) return false;
	
	console.log('addProduct',fk_product,label);
	if($('li.selectedElement>ul').length == 0)$('li.selectedElement').append('<ul container-type="nomenclature" />');
	$to = $('li.selectedElement>ul');
	
	if(label == '')label='...';
	$li = $('<li id="new-product-'+Math.floor(Math.random()*100000)+'" line-type="nomenclature" object_type="product" fk_object="'+fk_product+'" class="newElement">'+label+'</li>');
	
	$to.append($li);
	
}

