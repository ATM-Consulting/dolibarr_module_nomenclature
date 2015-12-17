$(document).ready(function() {
	//http://camohub.github.io/jquery-sortable-lists/
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
		  $('div.logme').html( );	
		}
	};

	$('#speednomenclature a').addClass('clicable');
	$('#speednomenclature').sortableLists( options );
	
});
