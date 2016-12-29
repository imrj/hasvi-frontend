<?php
/*
This is the settings page, based on http://wpsettingsapi.jeroensormani.com/ 
*/

add_action( 'admin_menu', 'Hasvi_add_admin_menu' );
add_action( 'admin_init', 'Hasvi_settings_init' );

function Hasvi_add_admin_menu(  ) { 

	add_menu_page( 'Hasvi', 'Hasvi', 'manage_options', 'Hasvi', 'Hasvi_options_page' );
    add_submenu_page('Hasvi', 'Hasvi', 'Users', 'manage_options', 'Hasvi-users', 'Hasvi_users_page' );

}


function Hasvi_settings_init(  ) { 

	register_setting( 'pluginPage', 'Hasvi_settings' );

	add_settings_section(
		'Hasvi_pluginPage_section', 
		__( 'Settings for Hasvi DynamoDB interface', 'wordpress' ), 
		'Hasvi_settings_section_callback', 
		'pluginPage'
	);

	add_settings_field( 
		'Hasvi_select_isProduction', 
		__( 'Use Debug or Production tables', 'wordpress' ), 
		'Hasvi_select_isProduction_render', 
		'pluginPage', 
		'Hasvi_pluginPage_section' 
	);

	add_settings_field( 
		'Hasvi_AWSKey', 
		__( 'AWS Key', 'wordpress' ), 
		'Hasvi_AWSKey_render', 
		'pluginPage', 
		'Hasvi_pluginPage_section' 
	);

	add_settings_field( 
		'Hasvi_AWSSecretKey', 
		__( 'AWS Secret Key', 'wordpress' ), 
		'Hasvi_AWSSecretKey_render', 
		'pluginPage', 
		'Hasvi_pluginPage_section' 
	);
    
    add_settings_field( 
		'Hasvi_AWSURL', 
		__( 'URL to AWS Hasvi-backend server', 'wordpress' ), 
		'Hasvi_AWSURL_render', 
		'pluginPage', 
		'Hasvi_pluginPage_section' 
	);
    
    add_settings_field( 
		'Hasvi_AWSRegion', 
		__( 'AWS Region', 'wordpress' ), 
		'Hasvi_AWSRegion_render', 
		'pluginPage', 
		'Hasvi_pluginPage_section' 
	);


}


function Hasvi_select_isProduction_render(  ) { 

	$options = get_option( 'Hasvi_settings' );
	?>
	<select name='Hasvi_settings[Hasvi_select_isProduction]'>
		<option value='1' <?php selected( $options['Hasvi_select_isProduction'], 1 ); ?>>Release</option>
		<option value='2' <?php selected( $options['Hasvi_select_isProduction'], 2 ); ?>>Debug</option>
	</select>

<?php

}

function Hasvi_AWSURL_render(  ) { 

	$options = get_option( 'Hasvi_settings' );
	?>
	<input type='text' size="50" name='Hasvi_settings[Hasvi_AWSURL]' value='<?php echo $options['Hasvi_AWSURL']; ?>'>
    <p>Must be of the form http://your.site.com/</p>
	<?php

}

function Hasvi_AWSKey_render(  ) { 

	$options = get_option( 'Hasvi_settings' );
	?>
	<input type='text' size="50" name='Hasvi_settings[Hasvi_AWSKey]' value='<?php echo $options['Hasvi_AWSKey']; ?>'>
    <p>This key must have read and write permissions to DynamoDB.</p>
	<?php

}


function Hasvi_AWSSecretKey_render(  ) { 

	$options = get_option( 'Hasvi_settings' );
	?>
	<input type='text' size="50" name='Hasvi_settings[Hasvi_AWSSecretKey]' value='<?php echo $options['Hasvi_AWSSecretKey']; ?>'>
	<?php

}

function Hasvi_AWSRegion_render(  ) { 

	$options = get_option( 'Hasvi_settings' );
	?>
	<input type='text' size="50" name='Hasvi_settings[Hasvi_AWSRegion]' value='<?php echo $options['Hasvi_AWSRegion']; ?>'>
	<p>AWS Region of the DynamoDb table. For example: ap-southeast-2.</p>
    <?php

}


function Hasvi_settings_section_callback(  ) { 

	echo __( 'This is the page for Hasvi Settings', 'wordpress' );

}

//Check the AWS connection settings
function Hasvi_settings_checkAWS() {
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

function Hasvi_options_page(  ) { 

	?>
	<form action='options.php' method='post'>

		<?php
		settings_fields( 'pluginPage' );
		do_settings_sections( 'pluginPage' );
        submit_button();
        
        Hasvi_settings_checkAWS();
        
		
		?>

	</form>
	<?php

}

// The edit user page on the options. Uses ajax.
function Hasvi_users_page(  ) { 
    wp_enqueue_script( 'hasvi.admin' );
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
