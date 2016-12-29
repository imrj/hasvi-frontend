<?php
/**
 * AWS prep work and other utilities
 */
 
/**
* Get names of tables and indexes, dependent upon whether we're in debug or release mode
*/
function aws_getTableName($tablename) {
    $options = get_option( 'Hasvi_settings' );
    
    //the indexes
    if($tablename == "GSItableNameStream") {
        return "username-hash-index";
    }   
    if($tablename == "GSItableNameView") {
        return "username-subURL-index";
    } 
    
    //release
    if($options['Hasvi_select_isProduction'] == 1) {
        if($tablename == "tableNameAC") {
            return "userAccounts";
        }
        if($tablename == "tableNameStream") {
            return "streams";
        }
        if($tablename == "tableNameView") {
            return "views";
        }
        if($tablename == "tableNameIOT") {
            return "IOTData2";
        }
    }  
    else if($options['Hasvi_select_isProduction'] == 2) {
        if($tablename == "tableNameAC") {
            return "testing-userAccounts";
        }
        if($tablename == "tableNameStream") {
            return "testing-streams";
        }
        if($tablename == "tableNameView") {
            return "testing-views";
        }
        if($tablename == "tableNameIOT") {
            return "testing-IOTData2";
        }
    }     
}

/**
* Get Hasvi URL of AWS, dependent upon debug/release mode
*/
function aws_getLinkName() {
    $options = get_option( 'Hasvi_settings' );
    
    return $options['Hasvi_AWSURL'];
    
}

/**
 * Setup connection to AWS DynamoDB, if required
 */
function aws_prep() {
    global $dynamodb;
    
    //If the db lib is already set up, then skip the rest
    if (isset($dynamodb)) {
        return;
    }
          
    try {
        //get the user settings
        $options = get_option( 'Hasvi_settings' );
        $sdk = new Aws\Sdk([
            'region'   => $options['Hasvi_AWSRegion'],
            'version'  => 'latest',
            'credentials' => [
                'key'    => $options['Hasvi_AWSKey'],
                'secret' => $options['Hasvi_AWSSecretKey'],
        ],
        ]);
    
        $dynamodb = $sdk->createDynamoDb();
    } catch (Exception $e) {
        outputAWSError($e)
    }
}

/**
* Helper function to output AWS error
*/
function outputAWSError(Exception $e) {
    $options = get_option( 'Hasvi_settings' );
    if($options['Hasvi_select_isProduction'] == 1) {
        $jTableResult['Result'] = "Error";
        $jTableResult['Message'] = "Internal error";
    }
    else {
       $jTableResult['Result'] = "Error";
       $jTableResult['Message'] = $e->getMessage();
    }
    
    return wp_send_json($jTableResult); 
}

/**
* Helper function to avoid any xss issues on user input
* By stripping any bad characters
*/ 
function safeUserInput($inputty) {
    return trim(htmlspecialchars($inputty, ENT_QUOTES | ENT_HTML401, 'UTF-8'));
}

/**
* Helper function to output AWS error
*/
function outputNoUser() {
    $jTableResult['Result'] = 'Error';
    $jTableResult['Message'] = "This user does not have an account";
    wp_send_json($jTableResult);
}
 
 /**
 * Helper function to check if user if logged in
 */
 function checkUserLogin() {
    global $user_login;
    if ($user_login == '') {
        $jTableResult['Result'] = "Error";
        $jTableResult['Message'] = "No user logged in";
        wp_send_json($jTableResult);
        return;
    }
 }
 
?>
