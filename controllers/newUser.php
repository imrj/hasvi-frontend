<?php
/*
Sync the new user or delete user actions to the back-end database
*/

/**
* Trigger from when a new user is created on the wordpress site
*/
add_action( 'user_register', 'hd_newUser' );
function hd_newUser( $user_id ) { 
    //hd_newUseraddtop(0, $user_id);
    $user_info = get_userdata($user_id);
    $options = get_option( 'Hasvi_settings' );
    
    hd_newUseradd( $user_info->user_login, $options['Hasvi_maxStreamLength'], $options['maxStreams'], $options['maxViews'], $options['minRefresh'], "0");
}

/**
* add the user at a particualr level
* levels can be 3 (Gold), 2 (Silver), 3 (Bronze) or else free
*/
function hd_newUseraddtop($level, $user_id) {
    if($level == 3) {
        $maxStreamLength = "5000";
        $maxStreams = "100";
        $maxViews = "100";
        $minRefresh = "10";
        $timeOut = "0";
    }
    else if($level == 2) {
        $maxStreamLength = "2000";
        $maxStreams = "50";
        $maxViews = "50";
        $minRefresh = "60";
        $timeOut = "0";
    }
    else if($level == 1) {
        $maxStreamLength = "1000";
        $maxStreams = "20";
        $maxViews = "20";
        $minRefresh = "300";
        $timeOut = "0";
    }
    else {
        $maxStreamLength = "500";
        $maxStreams = "10";
        $maxViews = "10";
        $minRefresh = "600";
        $timeOut = "0";
    }
    
    $user_info = get_userdata($user_id);
    hd_newUseradd( $user_info->user_login, $maxStreamLength, $maxStreams, $maxViews, $minRefresh, $timeOut);
}

/**
* Add the given username/options to the user accounts table
*/
function hd_newUseradd( $user_name, $maxStreamLength, $maxStreams, $maxViews, $minRefresh, $timeOut) {
    global $dynamodb;
    aws_prep();
    
    try {
        //insert into database
        $responseNewUser = $dynamodb->putItem([
            'TableName' => aws_getTableName("tableNameAC"),
            'Item' => [
            'username' => ['S' => $user_name],
            'active' => ['BOOL' => True],
            'maxStreamLength' => ['N' => $maxStreamLength],
            'maxStreams' => ['N' => $maxStreams],
            'maxViews' => ['N' => $maxViews],
            'minRefresh' => ['N' => $minRefresh],
            'timeOut' => ['N' => $timeOut],
        ]]);
        
    } catch (Exception $e) {
        return $e->getMessage();
    } 
}

/**
* remove the user from the back-end when their account is deleted
*/
add_action( 'delete_user', 'hd_delUser' );
function hd_delUser( $user_id ) { 
    $user_info = get_userdata($user_id);
    
    global $dynamodb;
    aws_prep();
    
    //need to reset the streams first ... execute url on node.js backend
    
    //delete all from streams, views and accounts table
    //Query the streams tables
    $responseStream = $dynamodb->query([
        'TableName' => aws_getTableName("tableNameStream"),
        'IndexName'=> aws_getTableName("GSItableNameStream"),
        'KeyConditionExpression' => 'username = :v_id',
        'ExpressionAttributeValues' =>  [
            ':v_id' => ['S' => $user_info->user_login]
        ]
    ]);
    
    foreach ($responseStream['Items'] as $key => $StreamIterator) {
        try {
            //delete from database
            $responseDelStream = $dynamodb->deleteItem([
                'TableName' => aws_getTableName("tableNameStream"),
                'Key' => [
                    'hash' => ['S' => $StreamIterator['hash']['S']]
                    ]
            ]);
        } catch (Exception $e) {
            outputAWSError($e);
            return;
        } 
    
    }
    
    //Query the views table
    $responseView = $dynamodb->query([
        'TableName' => aws_getTableName("tableNameView"),
        'IndexName'=> aws_getTableName("GSItableNameView"),
        'KeyConditionExpression' => 'username = :v_id',
        'ExpressionAttributeValues' =>  [
            ':v_id' => ['S' => $user_info->user_login]
        ]
    ]);
    
    foreach ($responseView['Items'] as $key => $ViewIterator) {
        try {
            //delete from database
            $responseDelView = $dynamodb->deleteItem([
                'TableName' => aws_getTableName("tableNameView"),
                'Key' => [
                    'subURL' => ['S' => $ViewIterator['subURL']['S']],
                    'username' => ['S' => $ViewIterator['username']['S']]
                ]
            ]);
        } catch (Exception $e) {
            outputAWSError($e);
            return;
        } 
    
    }

    //finally the accounts table
    try {
        //delete from database
        $responseDelAc = $dynamodb->deleteItem([
            'TableName' => aws_getTableName("tableNameAC"),
            'Key' => [
                'username' => ['S' => $user_info->user_login],
            ]
        ]);
    } catch (Exception $e) {
        outputAWSError($e);
        return;
    }
    
}

?>
