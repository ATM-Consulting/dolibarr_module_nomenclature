// Pour info la fonction getNumber sert juste à eviter un bug d'affichage pourris genre 5.56 - 0 = 5.5599999999999 

$( function() { 
    $( ".qtyConsume" ).bind("keyup change", function(e) {
        var datakey = $(this).data("targetkey");
        updateImpact(datakey);
    });


    $( ".stockAllowed" ).bind("keyup change", function(e) {
        var datakey = $(this).data("targetkey");
        updateImpact(datakey);
    });



    $( ".loadPlanned" ).click(function(e) {
        var datakey = $(this).data("targetkey");
         $("#qty-consume" + datakey ).val(  $("#diff-qty" + datakey).val()  ).trigger("change");
    });


    $( ".loadAllPlanned" ).click(function(e) {

        var formid = $(this).parents('form').attr("id");
    	
        $( "#" + formid + " .loadPlanned" ).each(function(e) {
            var datakey = $(this).data("targetkey");
             $("#qty-consume" + datakey ).val(  $("#diff-qty" + datakey).val()  ).trigger("change");
        });
    });


    $( ".loadAllowed" ).click(function(e) {
        var datakey = $(this).data("targetkey");

        var qty = $("#start-qty" + datakey).val();
        var stockAllowed =$("#stockAllowed" + datakey ).val();

        if(stockAllowed < qty){
            $("#stockAllowed" + datakey ).val(  qty  ).trigger("change");
        }
    });

    $( ".loadAllAllowed" ).click(function(e) {

        var formid = $(this).parents('form').attr("id");
    	
        $( "#" + formid + " .stockAllowed" ).each(function(e) {
            var datakey = $(this).data("targetkey");

            var qty = $("#start-qty" + datakey).val();
            var stockAllowed =$("#stockAllowed" + datakey ).val();

            if(stockAllowed < qty){
                $("#stockAllowed" + datakey ).val(  qty  ).trigger("change");
            }
        });
    });
    
    $( ".DoStockFeedBack" ).click(function(e) {

        var formid = $(this).parents('form').attr("id");
    	
        $( "#" + formid + " .stockAllowed" ).each(function(e) {
            var datakey = $(this).data("targetkey");
            updateFeedBack(datakey);
        });
    });
    


    // Forces signing on a number, returned as a string
    function getNumberSigned(theNumber)
    {
        if(theNumber < 0){
            return theNumber.toString();
        }else{
            return "+" + theNumber;
        }
    }

    function getNumber(theNumber)
    {
        return Math.round(parseFloat(theNumber)*10000)/10000;
    }

    function updateImpact(datakey){
        var line = $("#line" + datakey);
        
        var newstockallowed = parseFloat( $("#stockAllowed"  + datakey ).val());
        var stockallowed    = parseFloat(line.data("stockallowed"));
        var impactallowed   = getNumberSigned( newstockallowed - stockallowed );
        if(newstockallowed - stockallowed == 0){ impactallowed = ""; }
        $("#qty-allowed-impact" + datakey).html( impactallowed );


        var consume         = parseFloat( $("#qty-consume" + datakey).val());
        var qtyused         = parseFloat(line.data("qtyused")); 
        var finalused       = getNumber(   qtyused + consume  );
        var impactdispo     = getNumberSigned(  (newstockallowed - stockallowed ) - (finalused - qtyused) ) + " (" + (newstockallowed - finalused) + ")";
        
        if((newstockallowed - stockallowed ) - (finalused - qtyused)  == 0){ impactdispo = ""; }
        $("#qty-diff-impact" + datakey).html( impactdispo );

        var impactused      = getNumberSigned( consume ) + " (" + finalused + ")";
        if(consume == 0){ impactused      = ""; }
        $("#qty-used-impact" + datakey).html( impactused );

        // update imput limitation
        $("#stockAllowed"  + datakey ).attr("min",finalused);

    }

    function updateFeedBack(datakey){
        var line = $("#line" + datakey);
        var consume         = parseFloat( $("#qty-consume" + datakey).val());
        var qtyused         = parseFloat(line.data("qtyused")); 
        var finalused       = getNumber(   qtyused + consume  );
        $("#stockAllowed" + datakey ).val(  finalused  ).trigger("change");

    }
    
    
});