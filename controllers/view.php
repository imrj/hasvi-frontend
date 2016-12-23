<?php
/*
This is the stream page, works with JSON input and output
*/

/**
 * Get view accounts via AJAX.
 */
function hd_list_view_callback() {
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
                'TableName' => aws_getTableName("tableNameView"),
                'IndexName'=> aws_getTableName("GSItableNameView"),
                'KeyConditionExpression' => 'username = :v_id',
                'ExpressionAttributeValues' =>  [
                    ':v_id' => ['S' => $user_login]
                ]
            ];
            
            # Add the ExclusiveStartKey if we got one back in the previous response
            if(isset($responseView) && isset($responseView['LastEvaluatedKey'])) {
                $request['ExclusiveStartKey'] = $responseView['LastEvaluatedKey'];
            }

            $responseView = $dynamodb->query($request);
            #put data in
            foreach ($responseView['Items'] as $key => $ViewIterator) {
                $newdata = array(
                    'Token'  => $ViewIterator['hash']['S'],
                    'Name' => $ViewIterator['subURL']['S'],
                    'Format' => $ViewIterator['type']['S'],
                    'Timezone' => isset($ViewIterator['timezone']['N']) ? $ViewIterator['timezone']['N'] : '0',
                    'View_URL' => hd_createViewUrl($user_login, $ViewIterator['subURL']['S'], $ViewIterator['type']['S']),
                );
            
                array_push($ret_data, $newdata);
            }
            # If there is no LastEvaluatedKey in the response, then 
            # there are no more items matching this Query    
        } while(isset($responseView['LastEvaluatedKey'])); 
    } catch (Exception $e) {
        outputAWSError($e);
        return;
    }   
    $jTableResult = array();
    $jTableResult['Records'] = $ret_data;
    $jTableResult['Result'] = "OK";
    echo json_encode( $jTableResult );

	wp_die(); // this is required to terminate immediately and return a proper response
}
add_action( 'wp_ajax_hd_list_view', 'hd_list_view_callback' );

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

/**
 * Create new view account via AJAX.
 */
function hd_create_view_callback() {
	global $dynamodb;
	global $user_login;
    
    //no user logged in - return error
    checkUserLogin();
    aws_prep();
    
    $newViewname = '';
    $newViewname = isset($_POST['Name']) ? safeUserInput($_POST['Name']) : '';
    //ensure that viewname is only letters and numbers
    $newViewname = preg_replace("/[^A-Za-z0-9]/", '', $newViewname);
    $newViewToken = '';
    $newViewToken = isset($_POST['Token']) ? safeUserInput($_POST['Token']) : '';
    $newViewFormat = '';
    $newViewFormat = isset($_POST['Format']) ? safeUserInput($_POST['Format']) : '';
    $newViewTimezone = '';
    $newViewTimezone = isset($_POST['Timezone']) ? safeUserInput($_POST['Timezone']) : '';
    
    if($newViewname == '' or $newViewToken == '' or $newViewFormat == '') {
        $jTableResult = array();
        $jTableResult['Message'] = "Empty values " . $newViewname . ', ' . $newViewToken . ', ' . $newViewFormat . ', ' . $newViewTimezone;
        $jTableResult['Result'] = "Error";
        wp_send_json($jTableResult); 
        return;
    }
    
    //is the user at their max view limit?
    //also, is it a valid stream?
    //Query the view tables
    try {
        $responseAC = $dynamodb->query([
            'TableName' => aws_getTableName("tableNameAC"),
            'KeyConditionExpression' => 'username = :v_id',
            'ExpressionAttributeValues' =>  [
                ':v_id' => ['S' => $user_login]
            ]
        ]);
        
        $responseView = $dynamodb->query([
            'TableName' => aws_getTableName("tableNameView"),
            'IndexName'=> aws_getTableName("GSItableNameView"),
            'KeyConditionExpression' => 'username = :v_id',
            'ExpressionAttributeValues' =>  [
                ':v_id' => ['S' => $user_login]
            ],
            'Select' => 'COUNT'
        ]);
        
        $responseStream = $dynamodb->getItem([
            'TableName' => aws_getTableName("tableNameStream"),
            'Key' => [
                'hash' => ['S' => $newViewToken]
            ]
        ]);
    } catch (Exception $e) {
        outputAWSError($e);
        return;
    } 
        
    if($responseView['Count'] >= $responseAC['Items'][0]['maxViews']['N']) {
        $jTableResult['Result'] = "Error";
        $jTableResult['Message'] = "At view limit (" . $responseView['Count'] . " of " . $responseAC['Items'][0]['maxViews']['N'] . ")";
        echo json_encode( $jTableResult );
        wp_die();
        return;
    }
    
    if(!isset($responseStream['Item']) or $responseStream['Item']['username']['S'] != $user_login) {
        $jTableResult['Result'] = "Error";
        $jTableResult['Message'] = $newViewToken . " is not a valid token";
        echo json_encode( $jTableResult );
        wp_die();
        return;
    }
    
    //and create the view
    try {
        //insert into database
        $responseNewView = $dynamodb->putItem([
            'TableName' => aws_getTableName("tableNameView"),
            'Item' => [
            'hash' => ['S' => $newViewToken],
            'subURL' => ['S' => $newViewname],
            'type' => ['S' => $newViewFormat],
            'username' => ['S' => $user_login],
            'timezone' => ['N' => $newViewTimezone],
        ]]);
        
        //refresh streams query - get the item we just inserted
        $responseView = $dynamodb->getItem([
            'TableName' => aws_getTableName("tableNameView"),
            'Key' => [
                'subURL' => ['S' => $newViewname],
                'username' => ['S' => $user_login]
            ]
        ]);
        
        //format into a json response
        $newdata = array(
                'Token'  => $responseView['Item']['hash']['S'],
                'Name' => $responseView['Item']['subURL']['S'],
                'Format' => $responseView['Item']['type']['S'],
                'Timezone' => $responseView['Item']['timezone']['N'],
                'View_URL' => hd_createViewUrl($user_login, $responseView['Item']['subURL']['S'], $responseView['Item']['type']['S']),
        );
    } catch (Exception $e) {
        outputAWSError($e);
        return;
    } 
    
    $jTableResult = array();
    $jTableResult['Record'] = $newdata;
    $jTableResult['Result'] = "OK";
    wp_send_json($jTableResult);    
}
add_action( 'wp_ajax_hd_create_view', 'hd_create_view_callback' );


/**
 * Delete a view via AJAX.
 */
function hd_delete_view_callback() {
	global $dynamodb;
	global $user_login;
    
    //no user logged in - return error
    checkUserLogin();
    
    $delviewname = safeUserInput($_POST['Name']);
    if($delviewname == '') {
        $jTableResult = array();
        $jTableResult['Message'] = "Empty name";
        $jTableResult['Result'] = "Error";
        wp_send_json($jTableResult);
        return;
    }
    aws_prep();
    
    try {
        //delete from database
        $responseDelView = $dynamodb->deleteItem([
            'TableName' => aws_getTableName("tableNameView"),
            'Key' => [
                'subURL' => ['S' => $delviewname],
                'username' => ['S' => $user_login]
            ]
        ]);
        
    } catch (Exception $e) {
        outputAWSError($e);
        return;
    } 
    
    //send back just the OK
    $jTableResult = array();
    $jTableResult['Result'] = "OK";
    wp_send_json($jTableResult);
}
add_action( 'wp_ajax_hd_delete_view', 'hd_delete_view_callback' );

 /**
 * edit a view via AJAX.
 */
function hd_edit_view_callback() {
	global $dynamodb;
	global $user_login;
    
    //no user logged in - return error
    checkUserLogin();
    aws_prep();
    
    $editViewname = '';
    $editViewname = isset($_POST['Name']) ? safeUserInput($_POST['Name']) : '';
    //ensure that viewname is only letters and numbers
    $editViewname = preg_replace("/[^A-Za-z0-9]/", '', $editViewname);
    $editViewToken = '';
    $editViewToken = isset($_POST['Token']) ? safeUserInput($_POST['Token']) : '';
    $editViewFormat = '';
    $editViewFormat = isset($_POST['Format']) ? safeUserInput($_POST['Format']) : '';
    $newViewTimezone = '';
    $newViewTimezone = isset($_POST['Timezone']) ? safeUserInput($_POST['Timezone']) : '';
    
    if($editViewname == '' or $editViewToken == '' or $editViewFormat == '') {
        $jTableResult = array();
        $jTableResult['Message'] = "Empty values " . $editViewname . ', ' . $editViewToken . ', ' . $editViewFormat . ', ' . $newViewTimezone;
        $jTableResult['Result'] = "Error";
        wp_send_json($jTableResult); 
        return;
    }
    
    try {
        //edit in database
        $responseViewStream = $dynamodb->updateItem ([
            'TableName' => aws_getTableName("tableNameView"),
            'ExpressionAttributeNames' => ['#N' => 'hash', '#F' => 'type', '#T' => 'timezone' ],
            'ExpressionAttributeValues' => [
                ':val1' => ['S' => $editViewToken],
                ':val2' => ['S' => $editViewFormat],
                ':val3' => ['N' => $newViewTimezone],
            ],
            'UpdateExpression' => 'set #N = :val1, #F = :val2, #T = :val3',
            'Key' => [
                'subURL' => ['S' => $editViewname],
                'username' => ['S' => $user_login],
            ]
        ]);
        
        //refresh streams query - get the item we just inserted
        $responseView = $dynamodb->getItem([
            'TableName' => aws_getTableName("tableNameView"),
            'Key' => [
                'subURL' => ['S' => $editViewname],
                'username' => ['S' => $user_login]
            ]
        ]);
        
        //format into a json response
        $newdata = array(
                'Token'  => $responseView['Item']['hash']['S'],
                'Name' => $responseView['Item']['subURL']['S'],
                'Format' => $responseView['Item']['type']['S'],
                'Timezone' => isset($responseView['Item']['timezone']['N']) ? $responseView['Item']['timezone']['N'] : '0',
                'View_URL' => hd_createViewUrl($user_login, $responseView['Item']['subURL']['S'], $responseView['Item']['type']['S']),
        );
        
    } catch (Exception $e) {  
        return outputAWSError($e);
    }     
    
    //send back just the OK
    $jTableResult = array();
    $jTableResult['Record'] = $newdata;
    $jTableResult['Result'] = "OK";
    wp_send_json($jTableResult);
}
add_action( 'wp_ajax_hd_edit_view', 'hd_edit_view_callback' );

 /**
 * Create a view URL
 */
function hd_createViewUrl($user_name, $subURL, $filetype) {
    //format the file extension if required
    if($filetype == 'chartjs') {
        $fileExt = '';
    }
    else {
        $fileExt = '.' . $filetype;
    }
    
    return '<a href="'. aws_getLinkName(). 'views/' . $user_name . '/' . $subURL . $fileExt . '" target="_blank">'. aws_getLinkName(). 'views/' . $user_name . '/' . $subURL . $fileExt . '</a>';
}
?>