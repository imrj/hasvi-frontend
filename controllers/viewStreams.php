<?php
/*
This is a subset of the views page, for editing the streams in a view.
Works with JSON input and output
*/

/**
 * Get streams in a view via AJAX.
 */
function hd_list_view_streams_callback() {
	global $dynamodb;
	global $user_login;
    
    //no user logged in - return error
    checkUserLogin();
    
    aws_prep();
       
    $ret_data = array();
    
    $Viewname = '';
    $Viewname = isset($_POST['viewst']) ? safeUserInput($_POST['viewst']) : '';
    
    if($Viewname == '') {
        $jTableResult = array();
        $jTableResult['Message'] = "Empty viewname";
        $jTableResult['Result'] = "Error";
        wp_send_json($jTableResult); 
        return;
    }
    
    
    try {
        //Get the view
        $responseView = $dynamodb->getItem([
            'TableName' => aws_getTableName("tableNameView"),
            'Key' => [
                'subURL' => ['S' => $Viewname],
                'username' => ['S' => $user_login]
            ]
        ]);
        
        $viewstreamdata = array();
        
        #put data into the return array
        if (array_key_exists('tokensLeft', $responseView['Item'])) {
            foreach ($responseView['Item']['tokensLeft']['SS'] as $key => $ViewIterator) {
                $newdata = array(
                    'Token'  => $ViewIterator,
                    'Side'  => '1',
                    'compKey' => $ViewIterator . ',' . $Viewname . ',' . '1',
                    #'Name' => $CurStreamresponse['Item']['name']['S'],
                );
            
                array_push($viewstreamdata, $newdata);
            }
        }
        if (array_key_exists('tokensRight', $responseView['Item'])) {
            foreach ($responseView['Item']['tokensRight']['SS'] as $key => $ViewIterator) {
                $newdata = array(
                    'Token'  => $ViewIterator,
                    'Side'  => '0',
                    'compKey' => $ViewIterator . ',' . $Viewname . ',' . '0',
                    #'Name' => $CurStreamresponse['Item']['name']['S'],
                );
            
                array_push($viewstreamdata, $newdata);
            }
        }        
    
    } catch (Exception $e) {
        return outputAWSError($e);
    }     
    
    //send back the data
    $jTableResult = array();
    $jTableResult['Records'] = $viewstreamdata;
    $jTableResult['Result'] = "OK";
    wp_send_json($jTableResult);
 
}
add_action( 'wp_ajax_hd_list_view_streams', 'hd_list_view_streams_callback' );

/**
 * Create new stream in a view via AJAX.
 */
function hd_create_view_streams_callback() {
	global $dynamodb;
	global $user_login;
    
    //no user logged in - return error
    checkUserLogin();
    aws_prep();
    
    //check the inputs
    $newViewStreamToken = '';
    $newViewStreamToken = isset($_POST['Token']) ? safeUserInput($_POST['Token']) : '';
    $newViewStreamSide = '';
    $newViewStreamSide = isset($_POST['Side']) ? safeUserInput($_POST['Side']) : '';
    $Viewname = '';
    $Viewname = isset($_POST['viewst']) ? safeUserInput($_POST['viewst']) : '';
    
    if($Viewname == '' || $newViewStreamToken == '' || $newViewStreamSide == '') {
        $jTableResult = array();
        $jTableResult['Message'] = "Empty value";
        $jTableResult['Result'] = "Error";
        wp_send_json($jTableResult); 
        return;
    }
    
    //and add to the stream
    try {
        //Get the view
        $responseView = $dynamodb->getItem([
            'TableName' => aws_getTableName("tableNameView"),
            'Key' => [
                'subURL' => ['S' => $Viewname],
                'username' => ['S' => $user_login]
            ]
        ]);
        
        //get the old streams
        if (array_key_exists('tokensLeft', $responseView['Item'])) {
            $oldStreamsL = $responseView['Item']['tokensLeft']['SS'];
        }
        else {
            $oldStreamsL = [];        
        }
        if (array_key_exists('tokensRight', $responseView['Item'])) {
            $oldStreamsR = $responseView['Item']['tokensRight']['SS'];
        }
        else {
            $oldStreamsR = [];        
        }
        
        //add in the new stream and push to db
        if($newViewStreamSide == '1') {
            array_push($oldStreamsL, $newViewStreamToken);
            //edit in database
            $responseViewStream = $dynamodb->updateItem ([
                'TableName' => aws_getTableName("tableNameView"),
                'ExpressionAttributeNames' => ['#N' => 'tokensLeft',],
                'ExpressionAttributeValues' => [
                    ':val1' => ['SS' => $oldStreamsL],
                ],
                'UpdateExpression' => 'set #N = :val1',
                'Key' => [
                    'subURL' => ['S' => $Viewname],
                    'username' => ['S' => $user_login]
                ]
            ]);
        }
        else {
            array_push($oldStreamsR, $newViewStreamToken);
            //edit in database
            $responseViewStream = $dynamodb->updateItem ([
                'TableName' => aws_getTableName("tableNameView"),
                'ExpressionAttributeNames' => ['#N' => 'tokensRight',],
                'ExpressionAttributeValues' => [
                    ':val1' => ['SS' => $oldStreamsR],
                ],
                'UpdateExpression' => 'set #N = :val1',
                'Key' => [
                    'subURL' => ['S' => $Viewname],
                    'username' => ['S' => $user_login]
                ]
            ]);
        }

        
    } catch (Exception $e) {
        return outputAWSError($e);
    }

    //send back the insterted item
    $editdata = array(
        'Token'  => $newViewStreamToken,
        'Side'  => $newViewStreamSide,
        'compKey' => $newViewStreamToken . ',' . $Viewname . ',' . $newViewStreamSide,
    );
    $jTableResult = array();
    $jTableResult['Record'] = $editdata;
    $jTableResult['Result'] = "OK";
    wp_send_json($jTableResult);
    
}
add_action( 'wp_ajax_hd_create_view_streams', 'hd_create_view_streams_callback' );


/**
 * Delete a stream in a view via AJAX.
 */
function hd_delete_view_streams_callback() {
	global $dynamodb;
	global $user_login;
    
    //no user logged in - return error
    checkUserLogin();
    aws_prep();
    
    //check the inputs
    $delcompKey = '';
    $delcompKey = isset($_POST['compKey']) ? safeUserInput($_POST['compKey']) : '';
    $delViewStreamToken = explode(",",$delcompKey)[0];
    $Viewname = explode(",",$delcompKey)[1];
    $delViewStreamSide = explode(",",$delcompKey)[2];
    
    if($Viewname == '' || $delViewStreamToken == '' || $delViewStreamSide == '') {
        $jTableResult = array();
        $jTableResult['Message'] = "Empty value";
        $jTableResult['Result'] = "Error";
        wp_send_json($jTableResult); 
        return;
    }
    
    //and add to the stream
    try {
        //Get the view
        $responseView = $dynamodb->getItem([
            'TableName' => aws_getTableName("tableNameView"),
            'Key' => [
                'subURL' => ['S' => $Viewname],
                'username' => ['S' => $user_login]
            ]
        ]);
        
        //delete the view stream and update the db
        //http://stackoverflow.com/questions/4120589/remove-string-from-php-array
        if ($delViewStreamSide == '0' && array_key_exists('tokensRight', $responseView['Item'])) {
            $oldStreamsR = $responseView['Item']['tokensRight']['SS'];

            $index = array_search($delViewStreamToken,$oldStreamsR);
            if($index !== FALSE){
                unset($oldStreamsR[$index]);
            }
            $oldStreamsR = array_values($oldStreamsR);
            
            //edit in database. If no streams, delete the attribute
            if($oldStreamsR != []) {
                $responseViewStream = $dynamodb->updateItem ([
                    'TableName' => aws_getTableName("tableNameView"),
                    'ExpressionAttributeNames' => ['#N' => 'tokensRight',],
                    'ExpressionAttributeValues' => [
                        ':val1' => ['SS' => $oldStreamsR],
                    ],
                    'UpdateExpression' => 'set #N = :val1',
                    'Key' => [
                        'subURL' => ['S' => $Viewname],
                        'username' => ['S' => $user_login]
                    ]
                ]);
            }
            else {
                $responseViewStream = $dynamodb->updateItem ([
                    'TableName' => aws_getTableName("tableNameView"),
                    'ExpressionAttributeNames' => ['#N' => 'tokensRight',],
                    'UpdateExpression' => 'remove #N',
                    'Key' => [
                        'subURL' => ['S' => $Viewname],
                        'username' => ['S' => $user_login]
                    ]
                ]);            
            }
        
        }
        else if($delViewStreamSide == '1' && array_key_exists('tokensLeft', $responseView['Item'])) {
            $oldStreamsL = $responseView['Item']['tokensLeft']['SS'];

            $index = array_search($delViewStreamToken,$oldStreamsL);
            if($index !== FALSE){
                unset($oldStreamsL[$index]);
            }
            $oldStreamsL = array_values($oldStreamsL);
            
            //edit in database. If no streams, delete the attribute
            if($oldStreamsL != []) {
                $responseViewStream = $dynamodb->updateItem ([
                    'TableName' => aws_getTableName("tableNameView"),
                    'ExpressionAttributeNames' => ['#N' => 'tokensLeft',],
                    'ExpressionAttributeValues' => [
                        ':val1' => ['SS' => $oldStreamsL],
                    ],
                    'UpdateExpression' => 'set #N = :val1',
                    'Key' => [
                        'subURL' => ['S' => $Viewname],
                        'username' => ['S' => $user_login]
                    ]
                ]);
            }
            else {
                $responseViewStream = $dynamodb->updateItem ([
                    'TableName' => aws_getTableName("tableNameView"),
                    'ExpressionAttributeNames' => ['#N' => 'tokensLeft',],
                    'UpdateExpression' => 'remove #N',
                    'Key' => [
                        'subURL' => ['S' => $Viewname],
                        'username' => ['S' => $user_login]
                    ]
                ]);            
            }
        }
        else {
            $jTableResult = array();
            $jTableResult['Message'] = "Cannot find stream to delete";
            $jTableResult['Result'] = "Error";
            wp_send_json($jTableResult); 
            return;        
        }

    } catch (Exception $e) {
        return outputAWSError($e);
    }   
    
    
    //send back just the OK
    $jTableResult = array();
    $jTableResult['Result'] = "OK";
    wp_send_json($jTableResult);
    
}
add_action( 'wp_ajax_hd_delete_view_streams', 'hd_delete_view_streams_callback' );

/**
 * Get all valid streams to fill option boxes for views via AJAX.
 */
function hd_list_viewstreamoptions_callback() {
	global $dynamodb;
	global $user_login;
    
    //no user logged in - return error
    checkUserLogin();
    
    aws_prep();
       
    $ret_data = array();
    
    	try {
        //Query the tables
        $responseAC = $dynamodb->query([
            'TableName' => aws_getTableName("tableNameAC"),
            'KeyConditionExpression' => 'username = :v_id',
            'ExpressionAttributeValues' =>  [
                ':v_id' => ['S' => $user_login]
            ],
            'Select' => 'COUNT'
        ]);
        
        if($responseAC['Count'] !== 1) {
            outputNoUser();
            return;
        }
           
        //go through the views
        //get the view size - needs to be paginated
        do {
            $request = [
                'TableName' => aws_getTableName("tableNameStream"),
                'IndexName'=> aws_getTableName("GSItableNameStream"),
                'KeyConditionExpression' => 'username = :v_id',
                'ExpressionAttributeValues' =>  [
                    ':v_id' => ['S' => $user_login]
                ]
            ];
            
            # Add the ExclusiveStartKey if we got one back in the previous response
            if(isset($responseStream) && isset($responseStream['LastEvaluatedKey'])) {
                $request['ExclusiveStartKey'] = $responseStream['LastEvaluatedKey'];
            }

            $responseStream = $dynamodb->query($request);
            #put data in
            foreach ($responseStream['Items'] as $key => $StreamIterator) {
                $newdata = array(
                    'DisplayText'  => $StreamIterator['name']['S'],
                    'Value' => $StreamIterator['hash']['S'],
                );
            
                array_push($ret_data, $newdata);
            }
            # If there is no LastEvaluatedKey in the response, then 
            # there are no more items matching this Query    
        } while(isset($responseStream['LastEvaluatedKey'])); 
    } catch (Exception $e) {
        outputAWSError($e);
        return;
    }   
    
    $jTableResult = array();
    $jTableResult['Options'] = $ret_data;
    $jTableResult['Result'] = "OK";
    echo json_encode( $jTableResult );

	wp_die(); // this is required to terminate immediately and return a proper response
}
add_action( 'wp_ajax_hd_list_viewstreamoptions', 'hd_list_viewstreamoptions_callback' );

?>