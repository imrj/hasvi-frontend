<?php
/*
This is the views page, works with JSON input and output
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
    $newViewFormat = '';
    $newViewFormat = isset($_POST['Format']) ? safeUserInput($_POST['Format']) : '';
    $newViewTimezone = '';
    $newViewTimezone = isset($_POST['Timezone']) ? safeUserInput($_POST['Timezone']) : '';
    
    if($newViewname == '' or $newViewFormat == '') {
        $jTableResult = array();
        $jTableResult['Message'] = "Empty values " . $newViewname . ', ' . $newViewFormat . ', ' . $newViewTimezone;
        $jTableResult['Result'] = "Error";
        wp_send_json($jTableResult); 
        return;
    }
    
    //is the user at their max view limit?
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
    
    //and create the view
    try {
        //insert into database
        $responseNewView = $dynamodb->putItem([
            'TableName' => aws_getTableName("tableNameView"),
            'Item' => [
            'subURL' => ['S' => $newViewname],
            'type' => ['S' => $newViewFormat],
            'username' => ['S' => $user_login],
            'timezone' => ['N' => $newViewTimezone],
        ]]);
        
        //refresh views query - get the item we just inserted
        $responseView = $dynamodb->getItem([
            'TableName' => aws_getTableName("tableNameView"),
            'Key' => [
                'subURL' => ['S' => $newViewname],
                'username' => ['S' => $user_login]
            ]
        ]);
        
        //format into a json response
        $newdata = array(
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
    $editViewFormat = '';
    $editViewFormat = isset($_POST['Format']) ? safeUserInput($_POST['Format']) : '';
    $newViewTimezone = '';
    $newViewTimezone = isset($_POST['Timezone']) ? safeUserInput($_POST['Timezone']) : '';
    
    if($editViewname == '' or $editViewFormat == '') {
        $jTableResult = array();
        $jTableResult['Message'] = "Empty values " . $editViewname . ', ' . $editViewFormat . ', ' . $newViewTimezone;
        $jTableResult['Result'] = "Error";
        wp_send_json($jTableResult); 
        return;
    }
    
    try {
        //edit in database
        $responseViewStream = $dynamodb->updateItem ([
            'TableName' => aws_getTableName("tableNameView"),
            'ExpressionAttributeNames' => ['#F' => 'type', '#T' => 'timezone' ],
            'ExpressionAttributeValues' => [
                ':val2' => ['S' => $editViewFormat],
                ':val3' => ['N' => $newViewTimezone],
            ],
            'UpdateExpression' => 'set #F = :val2, #T = :val3',
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
 * Create a view URL. Include share buttons
 */
function hd_createViewUrl($user_name, $subURL, $filetype) {
    //format the file extension if required
    if($filetype == 'chartjs') {
        $fileExt = '';
    }
    else {
        $fileExt = '.' . $filetype;
    }
    
    return aws_getLinkName(). 'views/' . $user_name . '/' . $subURL . $fileExt;
    
}

?>