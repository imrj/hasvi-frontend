<?php
/*
Plugin Name: Hasvi for WordPress
Plugin URI: http://www.hasvi.com.au
Description: A wordpress interface to the Hasvi server.
Version: 1.0.1
Author: Stephen Dade
Author URI: http://www.hasvi.com.au
*/

//Note this issue on php 5.6:
//http://stackoverflow.com/questions/27870053/what-is-the-correct-way-to-send-a-json-string-between-javascript-and-php
//Need to edit the php.ini as required on cpanel

defined('ABSPATH') or die("No script kiddies please!");

// Global definitions
define( 'HASVI_PLUGIN_DIR', WP_PLUGIN_DIR . '/' . dirname(plugin_basename( __FILE__ )));

//Async process handler
include(HASVI_PLUGIN_DIR.'/wp-background-processing/wp-background-processing.php');

//Fatal error handler
include(HASVI_PLUGIN_DIR.'/controllers/errorhandler.php');

//AWS SDK
require HASVI_PLUGIN_DIR.'/aws.phar';

//AWS functions
include(HASVI_PLUGIN_DIR.'/controllers/aws.php');

//Special class to delete large streams in the background
include(HASVI_PLUGIN_DIR.'/controllers/deleteStream.php');

global $hd_delProcess;
$hd_delProcess = new HD_DeleteLargeStream();

//Create account on new user rego action
include(HASVI_PLUGIN_DIR.'/controllers/newUser.php');

//Admin pages
include(HASVI_PLUGIN_DIR.'/views/options.php');

//backend support for Admin page
include(HASVI_PLUGIN_DIR.'/controllers/adminUser.php');

//Stream json page
include(HASVI_PLUGIN_DIR.'/controllers/stream.php');

//Views json page
include(HASVI_PLUGIN_DIR.'/controllers/view.php');

//Account json page
include(HASVI_PLUGIN_DIR.'/controllers/account.php');

function install_hd_plugin(){
	//InstallController::install();
	//InstallController::addSampleData();
}

function desactivate_hd_plugin(){
}

function uninstall_hd_plugin(){
	//InstallController::delete();
}

/**
* Register any scripts and styles required on the page - Jtable, etc
*/
function addHDScripts() {
	wp_register_script( "jtable", plugins_url("js/jtable/jquery.jtable.js", __FILE__ ),
			array("jquery-ui-core", "jquery-ui-button", "jquery-ui-dialog", "jquery-effects-core") );               
    wp_register_script( "hasvi.streams", plugins_url("js/hasvi.streams.js", __FILE__ ), array("jtable"));
    wp_register_style( "hasvi.streams.ui", plugins_url("js/jtable/themes/base/jquery-ui.min.css", __FILE__ ));
    wp_register_style( "hasvi.streams.struct", plugins_url("js/jtable/themes/jqueryui/jquery-ui.structure.min.css", __FILE__ ));
    wp_register_style( "hasvi.streams.table.jq", plugins_url("js/jtable/themes/jqueryui/jtable_jqueryui.min.css", __FILE__ ));
    wp_register_style( "hasvi.streams.table.ui", plugins_url("js/jtable/themes/metro/darkgray/jtable.min.css", __FILE__ ));
    
}

/**
* Register any admin-only scripts and styles required
*/
function addHDScriptsAdmin () {
    wp_register_script( "hasvi.admin", plugins_url("js/hasvi.admin.js", __FILE__ ), array("jquery"));
}

/**
* Shortcode response for the streams table
*/
function hd_userstreamstable() {
    //localise the AJAX, then load the scripts and styles
    wp_enqueue_style( 'hasvi.streams.ui' );
    wp_enqueue_style( 'hasvi.streams.table.jq' );
    wp_localize_script( 'hasvi.streams', 'ajax_object', array( 'ajax_url' => admin_url( 'admin-ajax.php' )));
    wp_enqueue_script( 'hasvi.streams' );
            
    $html = '<div id="StreamsTableContainer"></div><div id="ErrorContainer"></div>';
    return $html;

}

/**
* Shortcode response for the views table
*/
function hd_userviewstable() {
    //localise the AJAX, then load the scripts and styles
    wp_enqueue_style( 'hasvi.streams.ui' );
    wp_enqueue_style( 'hasvi.streams.table.jq' );
    wp_localize_script( 'hasvi.streams', 'ajax_object', array( 'ajax_url' => admin_url( 'admin-ajax.php' )));
    wp_enqueue_script( 'hasvi.streams' );
            
    //$html = "<p>User Basic settings...</p>";
    $html = '<div id="ViewsTableContainer"></div><div id="ErrorContainer"></div>';
    return $html;

}

/**
* Shortcode response for account info table
*/
function hd_useraccounttable() {
    //localise the AJAX, then load the scripts and styles
    wp_enqueue_style( 'hasvi.streams.ui' );
    wp_enqueue_style( 'hasvi.streams.table.jq' );
    wp_localize_script( 'hasvi.streams', 'ajax_object', array( 'ajax_url' => admin_url( 'admin-ajax.php' )));
    wp_enqueue_script( 'hasvi.streams' );
            
    $html = '<div id=\'UsermaxStreams\'></div>';
    $html .= '<div id=\'UsermaxViews\'></div>';
    $html .= '<div id=\'UserminRefresh\'></div>';
    $html .= '<div id=\'UsertimeOut\'></div>';
    return $html;

}

// Hooks registration
register_activation_hook( __FILE__, 'install_hd_plugin' );
register_deactivation_hook( __FILE__, 'desactivate_hd_plugin' );
register_uninstall_hook( __FILE__, 'uninstall_hd_plugin' );

//Scripts registration
add_action( 'wp_enqueue_scripts', 'addHDScripts' );
add_action( 'admin_enqueue_scripts', 'addHDScriptsAdmin' );

//Register shortcodes
add_shortcode( 'hd_sTable', 'hd_userstreamstable' );
add_shortcode( 'hd_aTable', 'hd_useraccounttable' );
add_shortcode( 'hd_vTable', 'hd_userviewstable' );

?>
