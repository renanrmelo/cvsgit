<?php

define('BASE_DIR', dirname(__DIR__) . '/');
define('APPLICATION_DIR', BASE_DIR . 'application/');
define('SYSTEM_DIR', BASE_DIR . 'system/');
define('LIBRARY_DIR', SYSTEM_DIR . 'library/');

function __autoload($class) {

  $aFiles = require SYSTEM_DIR . 'cache/classmap.php';

  if ( empty($aFiles[ $class ]) ) {
    die("Arquivo não encontrado: $class\n");
  }

  require_once $aFiles[$class];
}
