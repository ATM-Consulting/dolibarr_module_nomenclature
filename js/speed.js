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
			if(cEl.hasClass('product') /*|| target.closest('li').length==0*/ ) {
				hint.css('background-color', '#ff9999');
				return false;
			}
			else {
				hint.css('background-color', '#99ff99');
				return true;
			}
		},
		onChange:function(cEl) {
		  console.log( $('#speednomenclature').sortableListsToArray());
		  console.log( $('#speednomenclature').sortableListsToHierarchy());
		  $('div.logme').html( );	
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
		addProduct($('select#fk_product').val(),$('select#fk_product option:selected').text());
	});
	
});

function addProduct(fk_product,label) {
	console.log('addProduct',fk_product,label);
	if($('li.selectedElement>ul').length == 0)$('li.selectedElement').append('<ul />');
	$to = $('li.selectedElement>ul');
	
	if(label == '')label='...';
	$li = $('<li id="'+fk_product+'" line-type="product" class="newElement">'+label+'</li>');
	$li.data('value', {
		fk_product:fk_product
	});
	
	$to.append($li);
	
}
