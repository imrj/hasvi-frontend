<?php
/*
This is the stream page, works with JSON input and output
*/

/**
 * Get stream accounts via AJAX.
 */
function hd_list_stream_callback() {
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
    
        //Query the streams tables
        $responseStream = $dynamodb->query([
            'TableName' => aws_getTableName("tableNameStream"),
            'IndexName'=> aws_getTableName("GSItableNameStream"),
            'KeyConditionExpression' => 'username = :v_id',
            'ExpressionAttributeValues' =>  [
                ':v_id' => ['S' => $user_login]
            ]
        ]);
               
        //http://localhost:1337/insertData?hash=gjt75iehdjf7rhg893e3&data=42
        foreach ($responseStream['Items'] as $key => $StreamIterator) {
            //get the stream size - needs to be paginated
            $streamCount = hd_getstreamCount($StreamIterator['hash']['S']);
            $minValue = array_key_exists('minValue', $StreamIterator) ? $StreamIterator['minValue']['N'] : '';
            $maxValue = array_key_exists('maxValue', $StreamIterator) ? $StreamIterator['maxValue']['N'] : '';
            
            //and add to array
            $newdata = array(
                'Token'  => $StreamIterator['hash']['S'],
                'Name' => $StreamIterator['name']['S'],
                'Data_URL' => '<a href="'. aws_getLinkName(). 'insertData?token=' . $StreamIterator['hash']['S'] . '&data=XXX">'. aws_getLinkName(). 'insertData?token=' . $StreamIterator['hash']['S'] . '&data=XXX</a>',
                //'Reset_URL' => 'http://data.hasvi.com/resetData?token=' . $StreamIterator['hash']['S'],
                'Stream_Usage' => $streamCount . '/' . $StreamIterator['maxStreamLength']['N'],
                'maxValue' => $maxValue,
                'minValue' => $minValue,
                //'Base_time' => 'date',
                //'Last_used' => 'date2',
            );
            array_push($ret_data, $newdata);
        }
    } catch (Exception $e) {
        outputAWSError($e);
        return;
    }   
    $jTableResult = array();
    $jTableResult['Records'] = $ret_data;
    $jTableResult['Result'] = "OK";
    wp_send_json($jTableResult);
}
add_action( 'wp_ajax_hd_list_stream', 'hd_list_stream_callback' );

/**
 * Create new stream account via AJAX.
 */
function hd_create_stream_callback() {
	global $dynamodb;
	global $user_login;
    
    //no user logged in - return error
    checkUserLogin();
    aws_prep();
    
    $newstreamname = '';
    $newstreamname = isset($_POST['Name']) ? safeUserInput($_POST['Name']) : '';
    $minValue = isset($_POST['minValue']) ? safeUserInput($_POST['minValue']) : '';
    $maxValue = isset($_POST['maxValue']) ? safeUserInput($_POST['maxValue']) : '';

    if($newstreamname == '') {
        $jTableResult = array();
        $jTableResult['Message'] = "Empty value";
        $jTableResult['Result'] = "Error";
        wp_send_json($jTableResult);
        return;
    }
    
    //is the user at their max stream limit?
    //Query the streams tables
    try {
        $responseAC = $dynamodb->query([
            'TableName' => aws_getTableName("tableNameAC"),
            'KeyConditionExpression' => 'username = :v_id',
            'ExpressionAttributeValues' =>  [
                ':v_id' => ['S' => $user_login]
            ]
        ]);
        
        $responseStream = $dynamodb->query([
            'TableName' => aws_getTableName("tableNameStream"),
            'IndexName'=> aws_getTableName("GSItableNameStream"),
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
        
     if($responseStream['Count'] >= $responseAC['Items'][0]['maxStreams']['N']) {
        $jTableResult['Result'] = "Error";
        $jTableResult['Message'] = "At stream limit (" . count($responseStream['Items']) . " of " . $responseAC['Items'][0]['maxStreams']['N'] . ")";
        wp_send_json($jTableResult);
        return;
    }
       
    try {
        //new stream gen
        //create a new token and check it's not already taken
        
        $characters = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $token = '';
        do {
            for ($i = 0; $i < 20; $i++) {
              $token .= $characters[rand(0, strlen($characters) - 1)];
            }
            $responseCheckNames = $dynamodb->query([
                'TableName' => aws_getTableName("tableNameStream"),
                'KeyConditionExpression' => '#hh = :h_id',
                'ExpressionAttributeNames'=> [ '#hh' => 'hash' ],
                'ExpressionAttributeValues' =>  [
                ':h_id' => ['S' => $token]],
                'Select' => 'COUNT'
            ]); 
        } while ($responseCheckNames['Count'] != 0);
    
        //insert into database
        $responseNewStream = $dynamodb->putItem([
            'TableName' => aws_getTableName("tableNameStream"),
            'Item' => [
            'hash' => ['S' => $token],
            'active' => ['BOOL' => True],
            'name' => ['S' => $newstreamname],
            'username' => ['S' => $user_login],
            'baseTime' => ['N' => (string)time()],
            'maxStreamLength' => ['N' => $responseAC['Items'][0]['maxStreamLength']['N']],
            'minRefresh' => ['N' => $responseAC['Items'][0]['minRefresh']['N']],
        ]]);
        
        //add min/max values as required
        if($minValue != '') {
            $response = $dynamodb->updateItem([
                'TableName' => aws_getTableName("tableNameStream"),
                'Key' => [
                    'hash' => ['S' => $token]
                ],
                'ExpressionAttributeValues' =>  [
                    ':val1' => ['N' => $minValue] 
                ] ,
                'UpdateExpression' => 'set minValue = :val1'
            ]);               
        }
        if($maxValue != '') {
            $response = $dynamodb->updateItem([
                'TableName' => aws_getTableName("tableNameStream"),
                'Key' => [
                    'hash' => ['S' => $token]
                ],
                'ExpressionAttributeValues' =>  [
                    ':val1' => ['N' => $maxValue] 
                ] ,
                'UpdateExpression' => 'set maxValue = :val1'
            ]);               
        }
        
        //refresh streams query - get the item we just inserted
        $responseStream = $dynamodb->getItem([
            'TableName' => aws_getTableName("tableNameStream"),
            'Key' => [
                'hash' => ['S' => $token]
            ]
        ]);
        
        //format into a json response
        $newdata = array(
            'Token'  => $responseStream['Item']['hash']['S'],
            'Name' => $responseStream['Item']['name']['S'],
            'Data_URL' => '<a href="'. aws_getLinkName(). 'insertData?token=' . $responseStream['Item']['hash']['S'] . '&data=XXX">'. aws_getLinkName(). 'insertData?token=' . $responseStream['Item']['hash']['S'] . '&data=XXX</a>',
            'Stream_Usage' => '0' . '/' . $responseStream['Item']['maxStreamLength']['N'],
            'maxValue' => array_key_exists('maxValue', $responseStream['Item']) ? $responseStream['Item']['maxValue']['N'] : '',
            'minValue' => array_key_exists('minValue', $responseStream['Item']) ? $responseStream['Item']['minValue']['N'] : ''
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
add_action( 'wp_ajax_hd_create_stream', 'hd_create_stream_callback' );

/**
 * Delete a stream via AJAX.
 */
function hd_delete_stream_callback() {
	global $dynamodb;
	global $user_login;
    global $hd_delProcess;
    
    //no user logged in - return error
    checkUserLogin();
    
    $delstreamtoken = safeUserInput($_POST['Token']);
    if($delstreamtoken == '') {
        $jTableResult = array();
        $jTableResult['Message'] = "Empty token";
        $jTableResult['Result'] = "Error";
        wp_send_json($jTableResult);
        return;
    }
    aws_prep();
       
    try {
        //if it's a big stream, tell the user this may take some time
        $streamCount = hd_getstreamCount($delstreamtoken);
        if($streamCount > 150) {
            $jTableResult = array();
            //$jTableResult['Message'] = "Stream will take a few minutes to delete";
            $jTableResult['Result'] = "OK";
            $hd_delProcess->push_to_queue( $delstreamtoken );
            $hd_delProcess->save()->dispatch();
            wp_send_json($jTableResult);
            return;
        }
        else
        {
            //start deleting since it's less than 150 items
            do {
                $request = [
                    'TableName' => aws_getTableName("tableNameIOT"),
                    'KeyConditionExpression' => '#hh = :h_id',
                    'ExpressionAttributeNames'=> [ '#hh' => 'hash' ],
                    'ExpressionAttributeValues' =>  [
                        ':h_id' => ['S' => $delstreamtoken]
                    ],
                    'Limit' => 50
                ];
                
                # Add the ExclusiveStartKey if we got one back in the previous response
                if(isset($responseStream) && isset($responseStream['LastEvaluatedKey'])) {
                    $request['ExclusiveStartKey'] = $responseStream['LastEvaluatedKey'];
                }

                $responseStream = $dynamodb->query($request);
                
                //and delete all items in the response
                foreach ($responseStream['Items'] as $key => $StreamIterator) {
                    $responseDelItem = $dynamodb->deleteItem([
                        'TableName' => aws_getTableName("tableNameIOT"),
                        'Key' => [
                            'hash' => ['S' => $StreamIterator['hash']['S']],
                            'datetime' => ['N' => $StreamIterator['datetime']['N']]
                            ]
                    ]);
                }
                
            } while(isset($responseStream['LastEvaluatedKey'])); 
        }
        
        //delete from database
        $responseDelStream = $dynamodb->deleteItem([
            'TableName' => aws_getTableName("tableNameStream"),
            'Key' => [
                'hash' => ['S' => $delstreamtoken]
                ]
        ]);
        
    } catch (Exception $e) {
        outputAWSError($e);
        wp_mail( 'stephen_dade@hotmail.com', 'Debug', $e );
        return;
    } 
    
    //send back just the OK
    $jTableResult = array();
    $jTableResult['Result'] = "OK";
    wp_send_json($jTableResult);
}
add_action( 'wp_ajax_hd_delete_stream', 'hd_delete_stream_callback' );
    
 /**
 * edit a stream name via AJAX.
 */
function hd_edit_stream_callback() {
	global $dynamodb;
	global $user_login;
    
    //no user logged in - return error
    checkUserLogin();
    
    $editstreamtoken = safeUserInput($_POST['Token']);
    $editstreamname = safeUserInput($_POST['Name']);
    $editminValue = isset($_POST['minValue']) ? safeUserInput($_POST['minValue']) : '';
    $editmaxValue = isset($_POST['maxValue']) ? safeUserInput($_POST['maxValue']) : '';

    if($editstreamname == '' or $editstreamtoken == '') {
        $jTableResult = array();
        $jTableResult['Message'] = "Empty value";
        $jTableResult['Result'] = "Error";
        wp_send_json($jTableResult);
        return;
    }
    
    aws_prep();
       
    try {
        //edit in database
        $responseEditStream = $dynamodb->updateItem ([
            'TableName' => aws_getTableName("tableNameStream"),
            'ExpressionAttributeNames' => ['#N' => 'name',],
            'ExpressionAttributeValues' => [
                ':val1' => ['S' => $editstreamname],
            ],
            'UpdateExpression' => 'set #N = :val1',
            'Key' => [
                'hash' => ['S' => $editstreamtoken],
                ]
        ]);
        
        //add min/max values as required
        if($editminValue != '') {
            $response = $dynamodb->updateItem([
                'TableName' => aws_getTableName("tableNameStream"),
                'Key' => [
                    'hash' => ['S' => $editstreamtoken]
                ],
                'ExpressionAttributeValues' =>  [
                    ':val1' => ['N' => $editminValue] 
                ] ,
                'UpdateExpression' => 'set minValue = :val1'
            ]);               
        }
        else {
            $response = $dynamodb->updateItem([
                'TableName' => aws_getTableName("tableNameStream"),
                'Key' => [
                    'hash' => ['S' => $editstreamtoken]
                ],
                'UpdateExpression' => 'remove minValue'
            ]);         
        }
        
        if($editmaxValue != '') {
            $response = $dynamodb->updateItem([
                'TableName' => aws_getTableName("tableNameStream"),
                'Key' => [
                    'hash' => ['S' => $editstreamtoken]
                ],
                'ExpressionAttributeValues' =>  [
                    ':val1' => ['N' => $editmaxValue] 
                ] ,
                'UpdateExpression' => 'set maxValue = :val1'
            ]);               
        }
        else {
            $response = $dynamodb->updateItem([
                'TableName' => aws_getTableName("tableNameStream"),
                'Key' => [
                    'hash' => ['S' => $editstreamtoken]
                ],
                'UpdateExpression' => 'remove maxValue'
            ]);         
        }
        
    } catch (Exception $e) {
        outputAWSError($e);
        return;
    } 
    
    //send back just the OK
    $jTableResult = array();
    $jTableResult['Result'] = "OK";
    wp_send_json($jTableResult);
}
add_action( 'wp_ajax_hd_edit_stream', 'hd_edit_stream_callback' );

/**
* Get number of items currently in a stream
*/
function hd_getstreamCount($token)
{
    global $dynamodb;
    $totCount = 0;
    do {
        $request = [
            'TableName' => aws_getTableName("tableNameIOT"),
            'KeyConditionExpression' => '#hh = :h_id',
            'ExpressionAttributeNames'=> [ '#hh' => 'hash' ],
            'ExpressionAttributeValues' =>  [
                ':h_id' => ['S' => $token]
            ],
            'Select' => 'COUNT',
            'Limit' => 1000
        ];
        
        # Add the ExclusiveStartKey if we got one back in the previous response
        if(isset($responseStreamCount) && isset($responseStreamCount['LastEvaluatedKey'])) {
            $request['ExclusiveStartKey'] = $responseStreamCount['LastEvaluatedKey'];
        }

        $responseStreamCount = $dynamodb->query($request);
        $totCount += intval($responseStreamCount['Count']);
        # If there is no LastEvaluatedKey in the response, then 
        # there are no more items matching this Query    
    } while(isset($responseStreamCount['LastEvaluatedKey'])); 

    return $totCount;
}
?>