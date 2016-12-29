<?php
/*
This is the fatal error handler, should only work in debug mode
*/

register_shutdown_function( "fatal_handler" );

function fatal_handler() {
  $options = get_option( 'Hasvi_settings' );
  
  //Only output the error message if we're in debug mode (2)
  if($options['Hasvi_select_isProduction'] == 1) {
    return;
  }
    
  $errfile = "unknown file";
  $errstr  = "shutdown";
  $errno   = E_CORE_ERROR;
  $errline = 0;

  $error = error_get_last();

  if( $error !== NULL) {
    $errno   = $error["type"];
    $errfile = $error["file"];
    $errline = $error["line"];
    $errstr  = $error["message"];

    //echo __(format_error( $errno, $errstr, $errfile, $errline));
    $jTableResult['Result'] = "Error";
    $jTableResult['Message'] = $errstr . ', ' . $errfile . ', ' .$errline;
    wp_send_json($jTableResult);
  }
}

function format_error( $errno, $errstr, $errfile, $errline ) {
  $trace = print_r( debug_backtrace( false ), true );

  $content = "
  <table>
  <thead><th>Item</th><th>Description</th></thead>
  <tbody>
  <tr>
    <th>Error</th>
    <td><pre>$errstr</pre></td>
  </tr>
  <tr>
    <th>Errno</th>
    <td><pre>$errno</pre></td>
  </tr>
  <tr>
    <th>File</th>
    <td>$errfile</td>
  </tr>
  <tr>
    <th>Line</th>
    <td>$errline</td>
  </tr>
  <tr>
    <th>Trace</th>
    <td><pre>$trace</pre></td>
  </tr>
  </tbody>
  </table>";

  return $content;
}
?>
