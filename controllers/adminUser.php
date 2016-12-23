<?php
/*
AJAX Support functions for admin user page
*/

/**
* AJAX support function to get account details
*/
function hd_admin_getaccount() {
	global $dynamodb;
    
    $usertoCheck = trim(htmlspecialchars($_POST['UsertoCheck']));
    
    aws_prep();
    
    try {
        $ret_data = array();
        //Query the tables
        $responseAC = $dynamodb->query([
            'TableName' => aws_getTableName("tableNameAC"),
            'KeyConditionExpression' => 'username = :v_id',
            'ExpressionAttributeValues' =>  [
                ':v_id' => ['S' => $usertoCheck]
            ]
        ]);
        
        if(count($responseAC['Items']) == 1) {
            //we have a user
            $ret_data = array(
                'active'  => $responseAC['Items'][0]['active']['BOOL'],
                'maxStreamLength' => $responseAC['Items'][0]['maxStreamLength']['N'],
                'maxStreams' => $responseAC['Items'][0]['maxStreams']['N'],
                'maxViews' => $responseAC['Items'][0]['maxViews']['N'],
                'minRefresh' => $responseAC['Items'][0]['minRefresh']['N'],
                'timeOut' => $responseAC['Items'][0]['timeOut']['N'],
            );
        }
    } catch (Exception $e) {
        outputAWSError($e);
    } 
    
    $jTableResult = array();
    $jTableResult['Records'] = $ret_data;
    $jTableResult['Result'] = "OK";
    echo json_encode( $jTableResult );

	wp_die(); // this is required to terminate immediately and return a proper response
}
add_action( 'wp_ajax_hd_admin_getaccount', 'hd_admin_getaccount' );

/**
* AJAX support function to saved edited account details
*/
function hd_admin_overrideaccount() {
       
    try {
        $usertoCheck = trim(htmlspecialchars($_POST['UsertoCheck']));
        $maxStreamLength = trim(htmlspecialchars($_POST['maxStreamLength']));
        $maxStreams = trim(htmlspecialchars($_POST['maxStreams']));
        $maxViews = trim(htmlspecialchars($_POST['maxViews']));
        $minRefresh = trim(htmlspecialchars($_POST['minRefresh']));
        $timeOut = trim(htmlspecialchars($_POST['timeOut']));
        $response = hd_newUseradd($usertoCheck, $maxStreamLength, $maxStreams, $maxViews, $minRefresh, $timeOut);

    } catch (Exception $e) {
        outputAWSError();
    } 
    
    //Just send an "OK" back
    $jTableResult = array();
    $jTableResult['Result'] = "OK";
    echo json_encode( $jTableResult );

	wp_die(); // this is required to terminate immediately and return a proper response
}
add_action( 'wp_ajax_hd_admin_overrideaccount', 'hd_admin_overrideaccount' );
  

?>