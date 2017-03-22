/* Script for Hasvi actions

Stephen Dade
*/

//has the streams list been changed?
var changedStreams = false;

//Load Facebook SDK
(function(d, s, id) {
  var js, fjs = d.getElementsByTagName(s)[0];
  if (d.getElementById(id)) return;
  js = d.createElement(s); js.id = id;
  js.src = "//connect.facebook.net/en_GB/sdk.js#xfbml=1&version=v2.8";
  fjs.parentNode.insertBefore(js, fjs);
}(document, 'script', 'facebook-jssdk'));

jQuery(document).ready(function() {
    //Show user account details
    request = jQuery.ajax({
        url: ajax_object.ajax_url.concat('?action=hd_getaccountdetails'),
        dataType: "JSON",
        success: function(json){
            // Update the input controls
            jQuery('#UsermaxStreams').html("<p>Using " + json.Record.numStreams + " of " + json.Record.maxStreams + " streams.</p>");
            jQuery('#UsermaxViews').html("<p>Using " + json.Record.numViews + " of " + json.Record.maxViews + " views.</p>");
            jQuery('#UserminRefresh').html("<p>Minimum time between stream inserts is " + json.Record.minRefresh + " sec.</p>");
            if(json.Record.timeOut == '0') {
                jQuery('#UsertimeOut').html("");  
            }
            else {
                var myDate = new Date( parseInt(json.Record.timeOut));
                jQuery('#UsertimeOut').html("<p>Account Renewal: " + myDate.toGMTString() + "</p>");
            }
        },       
        // Callback handler that will be called on failure
        error: function (jqXHR, textStatus, errorThrown){
            // Log the error to the console
            //console.error(
            //    "The following error occurred: "+
            //    textStatus, errorThrown
            //);
            jQuery('#UsermaxStreams').html(textStatus);
        }
    });
    
    //Prepare jTable for streams view
	jQuery('#StreamsTableContainer').jtable({
		title: 'Streams',
		paging: false,
		sorting: false,
        jqueryuiTheme: true,
        openChildAsAccordion: true,
        columnSelectable: false,
		actions: {
			listAction: ajax_object.ajax_url.concat('?action=hd_list_stream'),
			createAction: ajax_object.ajax_url.concat('?action=hd_create_stream'),
			updateAction: ajax_object.ajax_url.concat('?action=hd_edit_stream'),
			deleteAction: ajax_object.ajax_url.concat('?action=hd_delete_stream')
		},
		fields: {
			Name: {
				title: 'Stream Name',
				width: '25%'
			},
			Token: {
				title: 'Token',
				width: '23%',
                create: false,
				edit: false,
                key: true
			},
			Stream_Usage: {
				title: 'Usage',
				width: '5%',
				create: false,
				edit: false
			},
            Data_URL: {
				title: 'Data URL',
				width: '40%',
				create: false,
				edit: false,
                display: function (streamData) {
                    var img = '<input type="text" value="' + streamData.record.Data_URL + '" readonly style="width: 400px;">';
                    
                    return img;
                }
			},
            minValue: {
				title: 'Minimum Value',
				width: '0%',
				create: true,
				edit: true,
				key: false,
				visibility: 'hidden'
			},
            maxValue: {
				title: 'Maximum Value',
				width: '0%',
				create: true,
				edit: true,
				key: false,
				visibility: 'hidden'
			}
		},
        
        //need events to signal that the views stream option needs
        //to be reloaded
        recordAdded: function (event, data) {
            if (data.record) {
                changedStreams = true;
            }
        },
        recordDeleted: function (event, data) {
            if (data.record) {
                changedStreams = true;
            }
        },
	});

	//Load streams list
	jQuery('#StreamsTableContainer').jtable('load');
    
    //Prepare jTable for views view
	jQuery('#ViewsTableContainer').jtable({
		title: 'Views',
		paging: false,
		sorting: false,
        jqueryuiTheme: true,
        openChildAsAccordion: true,
		actions: {
			listAction: ajax_object.ajax_url.concat('?action=hd_list_view'),
			createAction: ajax_object.ajax_url.concat('?action=hd_create_view'),
			updateAction: ajax_object.ajax_url.concat('?action=hd_edit_view'),
			deleteAction: ajax_object.ajax_url.concat('?action=hd_delete_view')
		},
		fields: {
            Options: {
				title: 'Data',
				width: '2%',
				create: false,
				edit: false,
				display: function (ViewData) {
				    var img = jQuery('<span class="ui-icon ui-icon-caret-1-s"></span>');
				    img.click(function () {
				        jQuery('#ViewsTableContainer').jtable('openChildTable',
                            img.closest('tr'),
                            {
                                title: ViewData.record.Name + ' - Streams',
                                actions: {
                                    listAction: ajax_object.ajax_url.concat('?action=hd_list_view_streams'),
                                    deleteAction: ajax_object.ajax_url.concat('?action=hd_delete_view_streams'),
                                    createAction: ajax_object.ajax_url.concat('?action=hd_create_view_streams')
                                },
                                fields: {
                                    Token: {
                                        create: true,
                                        edit: false,
                                        title: 'Stream',
                                        width: '33%',
                                        options: function(data) {
                                            if(changedStreams == true) {
                                                //clear the options cache if required
                                                data.clearCache();
                                                changedStreams = false;
                                            }
                                            return ajax_object.ajax_url.concat('?action=hd_list_viewstreamoptions');
                                        }
                                    },
                                    Side: {
                                        edit: false,
                                        title: 'Y-Axis Side',
                                        width: '33%',
                                        options: { '1': 'Left', '0': 'Right' }
                                    },
                                    //composite key of [token,viewname,side]
                                    compKey: {
                                        type: 'hidden',
                                        key: true,
                                    },
                                    viewst: {
                                        type: 'hidden',
                                        defaultValue: ViewData.record.Name,
                                    }
                                }                            
                            }, function (data) { //opened handler
                                data.childTable.jtable('load', { viewst: ViewData.record.Name });
                            });                            
                    });
				    return img;
				}
            },
			Name: {
				title: 'View Name',
				width: '20%',
                create: true,
                key: true,
			},
            Timezone: {
				title: 'Timezone',
				width: '0%',
				create: true,
				edit: true,
                options: [{ Value: '-12', DisplayText: '-12' }, { Value: '-11', DisplayText: '-11' }, { Value: '-10', DisplayText: '-10' }, { Value: '-9', DisplayText: '-9' }, { Value: '-8', DisplayText: '-8' }, { Value: '-7', DisplayText: '-7' }, { Value: '-6', DisplayText: '-6' }, { Value: '-5', DisplayText: '-5' }, { Value: '-4', DisplayText: '-4' }, { Value: '-3', DisplayText: '-3' }, { Value: '-2', DisplayText: '-2' }, { Value: '-1', DisplayText: '-1' }, { Value: '0', DisplayText: '0 (UTC)' }, { Value: '1', DisplayText: '+1' }, { Value: '2', DisplayText: '+2' }, { Value: '3', DisplayText: '+3' }, { Value: '4', DisplayText: '+4' }, { Value: '5', DisplayText: '+5' }, { Value: '6', DisplayText: '+6' }, { Value: '7', DisplayText: '+7' }, { Value: '8', DisplayText: '+8' }, { Value: '9', DisplayText: '+9' }, { Value: '10', DisplayText: '+10' }, { Value: '11', DisplayText: '+11' }, { Value: '12', DisplayText: '+12' }],
                visibility: 'hidden'
            },
			Format: {
				title: 'Type',
				width: '0%',
				create: true,
				edit: true,
                options: ['svg','csv','html','chartjs', 'xlsx'],
                visibility: 'hidden'
			},
			RightYAxisTitle: {
				title: 'Right Y-axis title',
				width: '0%',
				create: true,
				edit: true,
                visibility: 'hidden'
			},
			LeftYAxisTitle: {
				title: 'Left Y-axis title',
				width: '0%',
				create: true,
				edit: true,
                visibility: 'hidden'
			},
            View_URL: {
				title: 'URL',
				width: '40%',
				create: false,
				edit: false,
                display: function (ViewData) {
                    return '<a href="' + ViewData.record.View_URL + '" target="_blank">' + ViewData.record.View_URL + '</a>'
                }
			},
            Share: {
				title: 'Share',
				width: '20%',
				create: false,
				edit: false,
                display: function (ViewData) {
                    return '<a class="resp-sharing-button__link" href="https://facebook.com/sharer/sharer.php?u=' + ViewData.record.View_URL + '" target="_blank" aria-label=""><div class="resp-sharing-button resp-sharing-button--facebook resp-sharing-button--small"><div aria-hidden="true" class="resp-sharing-button__icon resp-sharing-button__icon--solid"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M18.77 7.46H14.5v-1.9c0-.9.6-1.1 1-1.1h3V.5h-4.33C10.24.5 9.5 3.44 9.5 5.32v2.15h-3v4h3v12h5v-12h3.85l.42-4z"/></svg></div></div></a>' + '<a class="resp-sharing-button__link" href="https://twitter.com/intent/tweet/?text=' + ViewData.record.Name + '&amp;url=' + ViewData.record.View_URL + '" target="_blank" aria-label=""><div class="resp-sharing-button resp-sharing-button--twitter resp-sharing-button--small"><div aria-hidden="true" class="resp-sharing-button__icon resp-sharing-button__icon--solid"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M23.44 4.83c-.8.37-1.5.38-2.22.02.93-.56.98-.96 1.32-2.02-.88.52-1.86.9-2.9 1.1-.82-.88-2-1.43-3.3-1.43-2.5 0-4.55 2.04-4.55 4.54 0 .36.03.7.1 1.04-3.77-.2-7.12-2-9.36-4.75-.4.67-.6 1.45-.6 2.3 0 1.56.8 2.95 2 3.77-.74-.03-1.44-.23-2.05-.57v.06c0 2.2 1.56 4.03 3.64 4.44-.67.2-1.37.2-2.06.08.58 1.8 2.26 3.12 4.25 3.16C5.78 18.1 3.37 18.74 1 18.46c2 1.3 4.4 2.04 6.97 2.04 8.35 0 12.92-6.92 12.92-12.93 0-.2 0-.4-.02-.6.9-.63 1.96-1.22 2.56-2.14z"/></svg></div></div></a>' + '<a class="resp-sharing-button__link" href="https://reddit.com/submit/?url=' + ViewData.record.View_URL + '" target="_blank" aria-label=""><div class="resp-sharing-button resp-sharing-button--reddit resp-sharing-button--small"><div aria-hidden="true" class="resp-sharing-button__icon resp-sharing-button__icon--solid"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M24 11.5c0-1.65-1.35-3-3-3-.96 0-1.86.48-2.42 1.24-1.64-1-3.75-1.64-6.07-1.72.08-1.1.4-3.05 1.52-3.7.72-.4 1.73-.24 3 .5C17.2 6.3 18.46 7.5 20 7.5c1.65 0 3-1.35 3-3s-1.35-3-3-3c-1.38 0-2.54.94-2.88 2.22-1.43-.72-2.64-.8-3.6-.25-1.64.94-1.95 3.47-2 4.55-2.33.08-4.45.7-6.1 1.72C4.86 8.98 3.96 8.5 3 8.5c-1.65 0-3 1.35-3 3 0 1.32.84 2.44 2.05 2.84-.03.22-.05.44-.05.66 0 3.86 4.5 7 10 7s10-3.14 10-7c0-.22-.02-.44-.05-.66 1.2-.4 2.05-1.54 2.05-2.84zM2.3 13.37C1.5 13.07 1 12.35 1 11.5c0-1.1.9-2 2-2 .64 0 1.22.32 1.6.82-1.1.85-1.92 1.9-2.3 3.05zm3.7.13c0-1.1.9-2 2-2s2 .9 2 2-.9 2-2 2-2-.9-2-2zm9.8 4.8c-1.08.63-2.42.96-3.8.96-1.4 0-2.74-.34-3.8-.95-.24-.13-.32-.44-.2-.68.15-.24.46-.32.7-.18 1.83 1.06 4.76 1.06 6.6 0 .23-.13.53-.05.67.2.14.23.06.54-.18.67zm.2-2.8c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm5.7-2.13c-.38-1.16-1.2-2.2-2.3-3.05.38-.5.97-.82 1.6-.82 1.1 0 2 .9 2 2 0 .84-.53 1.57-1.3 1.87z"/></svg></div></div></a>';
                }
			}
		}
	});

	//Load views list
	jQuery('#ViewsTableContainer').jtable('load');

});
