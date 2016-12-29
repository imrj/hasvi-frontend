/* Script for Hasvi admin actions

Stephen Dade
*/

//button action for "check user" action
function hd_admin_onCheckClick() {
    // Fire off the ajax request
    request = jQuery.ajax({
        url: ajaxurl.concat('?action=hd_admin_getaccount'),
        type: "post",
        data: { UsertoCheck: jQuery('#UsertoCheck').val() }
    });
    
    //and wait for the response
    // Callback handler that will be called on success
    request.done(function (response, textStatus, jqXHR){
        // Update the input controls
        var responseParsed = jQuery.parseJSON(response);
        jQuery('#UsermaxStreamLength').val(responseParsed.Records.maxStreamLength);
        jQuery('#UsermaxStreams').val(responseParsed.Records.maxStreams);
        jQuery('#UsermaxViews').val(responseParsed.Records.maxViews);
        jQuery('#UserminRefresh').val(responseParsed.Records.minRefresh);
        jQuery('#UsertimeOut').val(responseParsed.Records.timeOut);
        
        if(responseParsed.Message) {
            jQuery('#userAccountTextOut').html(responseParsed.Message);
        } else {
            jQuery('#userAccountTextOut').html("<p>Got Data</p>");
        }
    });

    // Callback handler that will be called on failure
    request.fail(function (jqXHR, textStatus, errorThrown){
        // Log the error to the console
        //console.error(
        //    "The following error occurred: "+
        //    textStatus, errorThrown
        //);
        jQuery('#userAccountTextOut').html(textStatus);
    });
    
}

//button action for submitting user account changes
function hd_admin_onSubmitClick() {
    //alert("hello world");
    
    // Fire off the ajax request
    request = jQuery.ajax({
        url: ajaxurl.concat('?action=hd_admin_overrideaccount'),
        type: "post",
        data: { UsertoCheck: jQuery('#UsertoCheck').val(),  maxStreamLength: jQuery('#UsermaxStreamLength').val(),
            maxStreams: jQuery('#UsermaxStreams').val(), maxViews: jQuery('#UsermaxViews').val(),
            minRefresh: jQuery('#UserminRefresh').val(), timeOut: jQuery('#UsertimeOut').val()}
    });

    // Callback handler that will be called on success
    request.done(function (response, textStatus, jqXHR){
        // Log a message to the console
        jQuery('#userAccountTextOut').html(response);

    });
    
    // Callback handler that will be called on failure
    request.fail(function (jqXHR, textStatus, errorThrown){
        // Log the error to the console
        //console.error(
        //    "The following error occurred: "+
        //    textStatus, errorThrown
        //);
        jQuery('#userAccountTextOut').html(jqXHR.responseText);
    });
}
