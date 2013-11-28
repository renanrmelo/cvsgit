<?php
 /**
  * Debug bootstrap [ON/OFF]
  */
Debug::$bootstrap = false;

Debug::run();

class Debug {

  public static $bootstrap;

  public static function config() {

    ini_set('xdebug.collect_vars', 'on');
    ini_set('xdebug.collect_params', '4');
    ini_set('xdebug.dump_globals', 'on');
    ini_set('xdebug.show_local_vars', 'on');
    ini_set('xdebug.cli_color', 1);

    /**
     * Since superglobal variables should not change at runtime,
     * xdebug by default displays them only on the first error message, not in every error message.
     * If you want xdebug to repeat dumping the global variables on every error, use
     */
    ini_set('xdebug.dump_once', 'Off');

    /**
     * 0 - human
     * 1 - computer
     * 2 - html 
     */
    ini_set('xdebug.trace_format', '0');

    ini_set('xdebug.dump.SERVER', 'HTTP_HOST, SERVER_NAME');

    ini_set('xdebug.profiler_enable', 1);
    ini_set('xdebug.profiler_output_name', 'cachegrid.out');
    ini_set('xdebug.remote_autostart', 1);
    ini_set('xdebug.remote_enable', 1);
    ini_set('xdebug.remote_host', '127.0.0.1');
    ini_set('xdebug.remote_port', 9000);
    ini_set('xdebug.remote_handler', 'dbgp');
    ini_set('xdebug.remote_log', '/tmp/xdebug.log'); 
  }

  public static function run() {

    if ( !Debug::$bootstrap ) {
      return false;
    }

    echo "\n ---XDEBUG [ON]\n";

    Debug::config();

    register_shutdown_function(array('Debug', 'stop'));
    xdebug_start_trace('/tmp/trace.log');
  }  
  
  public function stop() {
    xdebug_stop_trace();
  }                

} 
