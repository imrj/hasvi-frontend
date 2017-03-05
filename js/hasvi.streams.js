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
            Options: {
				title: '',
				width: '2%',
				create: false,
				edit: false,
				display: function (streamData) {
				    var img = jQuery('<span class="ui-icon ui-icon-caret-1-s"></span>');
				    img.click(function () {
				        if (jQuery('#StreamsTableContainer').jtable('isChildRowOpen', img.closest('tr'))) {
                            jQuery('#StreamsTableContainer').jtable('closeChildRow', img.closest('tr'));
                        }
                        else {
				            var childRow = jQuery('#StreamsTableContainer').jtable('openChildRow', img.closest('tr'));
				            //childRow.find('td').html('<p>Max = ' + streamData.record.maxValue + ', Min = ' + streamData.record.minValue + '</p>');
				            var minValRow = '<tr><td>Minimum Value</td><td>' + streamData.record.minValue + '</td></tr>';
				            var maxValRow = '<tr><td>Maximum Value</td><td>' + streamData.record.maxValue + '</td></tr>';
				            childRow.find('td').html('<table style="width: 30%;"><tr><th>Option</th><th>Value</th></tr>' + maxValRow + minValRow + '</table>');
				        }
                    });
				    return img;
				}
			},
			Name: {
				title: 'Stream Name',
				width: '20%'
			},
			Token: {
				title: 'Token',
				width: '15%',
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
				width: '50%',
				create: false,
				edit: false
			},
            minValue: {
				title: 'Minimum Value',
				width: '15%',
				create: true,
				edit: true,
				key: false,
				visibility: 'hidden'
			},
            maxValue: {
				title: 'Maximum Value',
				width: '15%',
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
        recordUpdated: function (event, data) {
            //close the child row, if required. Allows for refresh of data in row
            var row = jQuery('#StreamsTableContainer').jtable('getRowByKey', data.record.Token);
	        if (jQuery('#StreamsTableContainer').jtable('isChildRowOpen', row)) {
                jQuery('#StreamsTableContainer').jtable('closeChildRow', row);
            }
        }
        
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
				title: '',
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
			// Token: {
				// title: 'Data Source',
				// width: '20%',
                // create: true,
				// edit: true,
                // options: function(data) {
                    // if(changedStreams == true) {
                        //clear the options cache if required
                        // data.clearCache();
                        // changedStreams = false;
                    // }
                    // return ajax_object.ajax_url.concat('?action=hd_list_viewstreamoptions');
                // }
			// },
            Timezone: {
				title: 'Timezone',
				width: '5%',
				create: true,
				edit: true,
                options: [{ Value: '-12', DisplayText: '-12' }, { Value: '-11', DisplayText: '-11' }, { Value: '-10', DisplayText: '-10' }, { Value: '-9', DisplayText: '-9' }, { Value: '-8', DisplayText: '-8' }, { Value: '-7', DisplayText: '-7' }, { Value: '-6', DisplayText: '-6' }, { Value: '-5', DisplayText: '-5' }, { Value: '-4', DisplayText: '-4' }, { Value: '-3', DisplayText: '-3' }, { Value: '-2', DisplayText: '-2' }, { Value: '-1', DisplayText: '-1' }, { Value: '0', DisplayText: '0 (UTC)' }, { Value: '1', DisplayText: '+1' }, { Value: '2', DisplayText: '+2' }, { Value: '3', DisplayText: '+3' }, { Value: '4', DisplayText: '+4' }, { Value: '5', DisplayText: '+5' }, { Value: '6', DisplayText: '+6' }, { Value: '7', DisplayText: '+7' }, { Value: '8', DisplayText: '+8' }, { Value: '9', DisplayText: '+9' }, { Value: '10', DisplayText: '+10' }, { Value: '11', DisplayText: '+11' }, { Value: '12', DisplayText: '+12' }]
            },
			Format: {
				title: 'Type',
				width: '5%',
				create: true,
				edit: true,
                options: ['svg','csv','html','chartjs']
			},
            View_URL: {
				title: 'URL',
				width: '40%',
				create: false,
				edit: false,
                display: function (ViewData) {
                    return '<a href="' + ViewData.record.View_URL + '" target="_blank">' + ViewData.record.View_URL + '</a><br />' + '<div class="fb-share-button" data-href="' + ViewData.record.View_URL + '" data-layout="button" data-mobile-iframe="true"><a class="fb-xfbml-parse-ignore" target="_blank" href="https://www.facebook.com/sharer/sharer.php?u=' + ViewData.record.View_URL + '&amp;src=sdkpreparse">Share</a></div>';
                }
			}
		}
	});

	//Load views list
	jQuery('#ViewsTableContainer').jtable('load');

});
