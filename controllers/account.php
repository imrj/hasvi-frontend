<?php
/*
This is the account page, works with JSON input and output
*/

/**
 * Get account info via AJAX.
 */
function hd_getaccountdetails_callback() {
	global $dynamodb;
	global $user_login;
    
    //no user logged in - return error
    checkUserLogin();
    
    aws_prep();
    
    try {
        $ret_data = array();
        //Query the tables
        $responseAC = $dynamodb->query([
            'TableName' => aws_getTableName("tableNameAC"),
            'KeyConditionExpression' => 'username = :v_id',
            'ExpressionAttributeValues' =>  [
                ':v_id' => ['S' => $user_login]
            ]
        ]);
        
        if(count($responseAC['Items']) != 1) {
            $jTableResult = array();
            $jTableResult['Message'] = "No user";
            $jTableResult['Result'] = "Error";
            wp_send_json($jTableResult);
            return;
        }
        
        //get the number of streams
        $responseStream = $dynamodb->query([
            'TableName' => aws_getTableName("tableNameStream"),
            'IndexName'=> aws_getTableName("GSItableNameStream"),
            'KeyConditionExpression' => 'username = :v_id',
            'ExpressionAttributeValues' =>  [
                ':v_id' => ['S' => $user_login]
            ],
            'Select' => 'COUNT'
        ]);
        
        //get the number of views
        $responseView = $dynamodb->query([
            'TableName' => aws_getTableName("tableNameView"),
            'IndexName'=> aws_getTableName("GSItableNameView"),
            'KeyConditionExpression' => 'username = :v_id',
            'ExpressionAttributeValues' =>  [
                ':v_id' => ['S' => $user_login]
            ],
            'Select' => 'COUNT'
        ]);
        
        //format the return array
        $ret_data = array(
            'active'  => $responseAC['Items'][0]['active']['BOOL'],
            'maxStreamLength' => $responseAC['Items'][0]['maxStreamLength']['N'],
            'maxStreams' => $responseAC['Items'][0]['maxStreams']['N'],
            'maxViews' => $responseAC['Items'][0]['maxViews']['N'],
            'minRefresh' => $responseAC['Items'][0]['minRefresh']['N'],
            'timeOut' => $responseAC['Items'][0]['timeOut']['N'],
            'numStreams' => $responseStream['Count'],
            'numViews' => $responseView['Count'],
        );           
        
    } catch (Exception $e) {
        outputAWSError($e);
    } 
    
    $jTableResult = array();
    $jTableResult['Record'] = $ret_data;
    $jTableResult['Result'] = "OK";
    wp_send_json($jTableResult);
}
add_action( 'wp_ajax_hd_getaccountdetails', 'hd_getaccountdetails_callback' );

