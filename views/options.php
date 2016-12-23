<?php
/*
This is the settings page, based on http://wpsettingsapi.jeroensormani.com/ 
*/

add_action( 'admin_menu', 'Hadavi2_add_admin_menu' );
add_action( 'admin_init', 'Hadavi2_settings_init' );

function Hadavi2_add_admin_menu(  ) { 

	add_menu_page( 'Hadavi2', 'Hadavi2', 'manage_options', 'hadavi2', 'Hadavi2_options_page' );
    add_submenu_page('hadavi2', 'Hadavi2', 'Users', 'manage_options', 'hadavi2-users', 'Hadavi2_users_page' );

}


function Hadavi2_settings_init(  ) { 

	register_setting( 'pluginPage', 'Hadavi2_settings' );

	add_settings_section(
		'Hadavi2_pluginPage_section', 
		__( 'Settings for Hadavi DynamoDB interface', 'wordpress' ), 
		'Hadavi2_settings_section_callback', 
		'pluginPage'
	);

	add_settings_field( 
		'Hadavi2_select_isProduction', 
		__( 'Use Debug or Production tables', 'wordpress' ), 
		'Hadavi2_select_isProduction_render', 
		'pluginPage', 
		'Hadavi2_pluginPage_section' 
	);

	add_settings_field( 
		'Hadavi2_AWSKey', 
		__( 'AWS Key', 'wordpress' ), 
		'Hadavi2_AWSKey_render', 
		'pluginPage', 
		'Hadavi2_pluginPage_section' 
	);

	add_settings_field( 
		'Hadavi2_AWSSecretKey', 
		__( 'AWS Secret Key', 'wordpress' ), 
		'Hadavi2_AWSSecretKey_render', 
		'pluginPage', 
		'Hadavi2_pluginPage_section' 
	);
    
    add_settings_field( 
		'Hadavi2_AWSURL', 
		__( 'URL to AWS Hasvi-backend server', 'wordpress' ), 
		'Hadavi2_AWSURL_render', 
		'pluginPage', 
		'Hadavi2_pluginPage_section' 
	);
    
    add_settings_field( 
		'Hadavi2_AWSRegion', 
		__( 'AWS Region', 'wordpress' ), 
		'Hadavi2_AWSRegion_render', 
		'pluginPage', 
		'Hadavi2_pluginPage_section' 
	);


}


function Hadavi2_select_isProduction_render(  ) { 

	$options = get_option( 'Hadavi2_settings' );
	?>
	<select name='Hadavi2_settings[Hadavi2_select_isProduction]'>
		<option value='1' <?php selected( $options['Hadavi2_select_isProduction'], 1 ); ?>>Release</option>
		<option value='2' <?php selected( $options['Hadavi2_select_isProduction'], 2 ); ?>>Debug</option>
	</select>

<?php

}

function Hadavi2_AWSURL_render(  ) { 

	$options = get_option( 'Hadavi2_settings' );
	?>
	<input type='text' size="50" name='Hadavi2_settings[Hadavi2_AWSURL]' value='<?php echo $options['Hadavi2_AWSURL']; ?>'>
    <p>Must be of the form http://your.site.com/</p>
	<?php

}

function Hadavi2_AWSKey_render(  ) { 

	$options = get_option( 'Hadavi2_settings' );
	?>
	<input type='text' size="50" name='Hadavi2_settings[Hadavi2_AWSKey]' value='<?php echo $options['Hadavi2_AWSKey']; ?>'>
    <p>This key must have read and write permissions to DynamoDB.</p>
	<?php

}


function Hadavi2_AWSSecretKey_render(  ) { 

	$options = get_option( 'Hadavi2_settings' );
	?>
	<input type='text' size="50" name='Hadavi2_settings[Hadavi2_AWSSecretKey]' value='<?php echo $options['Hadavi2_AWSSecretKey']; ?>'>
	<?php

}

function Hadavi2_AWSRegion_render(  ) { 

	$options = get_option( 'Hadavi2_settings' );
	?>
	<input type='text' size="50" name='Hadavi2_settings[Hadavi2_AWSRegion]' value='<?php echo $options['Hadavi2_AWSRegion']; ?>'>
	<p>AWS Region of the DynamoDb table. For example: ap-southeast-2.</p>
    <?php

}


function Hadavi2_settings_section_callback(  ) { 

	echo __( 'This is the page for Hadavi Settings', 'wordpress' );

}

//Check the AWS connection settings
function Hadavi2_settings_checkAWS() {
	global $dynamodb;
    global $user_login;
    
    echo __('<h3>Diagnostics</h3>');
    
    echo __('<h4>AWS Connection</h4>');
    $awsresp = "";
    try {
        aws_prep();
        $awsresp = "AWS Connection OK";
    }
    catch (Exception $e) {        
        $awsresp = $e->getMessage();
    }
    echo __('<p>' . $awsresp . '</p>');
    
    echo __('<h4>Table number of rows</h4>');
    
    //Query the accounts tables
    echo __('<p>'. aws_getTableName("tableNameAC") . ': ' . hd_getTableLen(aws_getTableName("tableNameAC")) . '</p>');
    echo __('<p>'. aws_getTableName("tableNameStream") . ': ' . hd_getTableLen(aws_getTableName("tableNameStream")) . '</p>');
    echo __('<p>'. aws_getTableName("tableNameView") . ': ' . hd_getTableLen(aws_getTableName("tableNameView")) . '</p>');
    echo __('<p>'. aws_getTableName("tableNameIOT") . ': ' . hd_getTableLen(aws_getTableName("tableNameIOT")) . '</p>');
    
    echo __('<h4>Global Indexes</h4>');
    echo __('<p>GSI: '. aws_getTableName("GSItableNameStream") . ': ' . hd_checkGSItableNameStream() . '</p>');
    echo __('<p>GSI: '. aws_getTableName("GSItableNameView") . ': ' . hd_checkGSItableNameView() . '</p>');
        
}

function hd_getTableLen($tablename) {
	global $dynamodb;
    
    aws_prep();
    
    $total = 0;
    $start_key = null;
    $params = [
        'TableName' => $tablename,
        'Count'     => true
    ];

    try {
        do {
            # Add the ExclusiveStartKey if we got one back in the previous response
            if(isset($response) && isset($response['LastEvaluatedKey'])) {
                $params['ExclusiveStartKey'] = $response['LastEvaluatedKey'];
            }

            $response = $dynamodb->scan($params);

            $total += intval($response['Count']);

        } while(isset($response['LastEvaluatedKey']));
    } catch (Exception $e) {
        //outputAWSError($e);
        return $e;
    }  

    return $total;
}

function hd_checkGSItableNameStream() {
	global $dynamodb;
    global $user_login;
    
    aws_prep();
    
    try{ 
        $request = [
            'TableName' => aws_getTableName("tableNameStream"),
            'IndexName'=> aws_getTableName("GSItableNameStream"),
            'KeyConditionExpression' => 'username = :v_id',
            'ExpressionAttributeValues' =>  [
                ':v_id' => ['S' => $user_login]
            ]
        ];
        
        $responseView = $dynamodb->query($request);
    } catch (Exception $e) {
        return $e;
    }  
    return $responseView['Count'];
}

function hd_checkGSItableNameView() {
	global $dynamodb;
    global $user_login;
    
    aws_prep();
    
    try{ 
        $request = [
            'TableName' => aws_getTableName("tableNameView"),
            'IndexName'=> aws_getTableName("GSItableNameView"),
            'KeyConditionExpression' => 'username = :v_id',
            'ExpressionAttributeValues' =>  [
                ':v_id' => ['S' => $user_login]
            ]
        ];
        
        $responseView = $dynamodb->query($request);
    } catch (Exception $e) {
        return $e;
    }  
    return $responseView['Count'];
}

function Hadavi2_options_page(  ) { 

	?>
	<form action='options.php' method='post'>

		<?php
		settings_fields( 'pluginPage' );
		do_settings_sections( 'pluginPage' );
        submit_button();
        
        Hadavi2_settings_checkAWS();
        
		
		?>

	</form>
	<?php

}

// The edit user page on the options. Uses ajax.
function Hadavi2_users_page(  ) { 
    wp_enqueue_script( 'hadavi.admin' );
    global $user_login;
    

	?>
    <h2>User Account Management</h2>
    <p>Using table: <?php echo aws_getTableName("tableNameAC"); ?></p>
    <h3>Manually add account</h3>
	<p>Username: <input type='text' id='UsertoCheck' value='<?php echo $user_login; ?>'></p>
    <input type="button" onclick="hd_admin_onCheckClick()" value="Check">
    <p>maxStreamLength: <input type='number' id='UsermaxStreamLength' min='1' value=''></p>
    <p>maxStreams: <input type='number' id='UsermaxStreams' min='1' value=''></p>
    <p>maxViews: <input type='number' id='UsermaxViews' min='1' value=''></p>
    <p>minRefresh: <input type='number' id='UserminRefresh' min='0' value=''></p>
    <p>timeOut: <input type='number' id='UsertimeOut' min='0' value=''></p>
    <p><input type="button" onclick="hd_admin_onSubmitClick()" value="Submit"></p>
    <div id="userAccountTextOut"></div>
    
	<?php

}

?>