<?php
require_once __DIR__ . '/../../system/bootstrap.php';
require_once APPLICATION_DIR . 'dbreleases/DBReleasesCommand.php';

define('PATH', __DIR__ . '/');

try {

  $oInput      = new \Symfony\Component\Console\Input\ArgvInput();
  $oOutput     = new \Symfony\Component\Console\Output\ConsoleOutput();
  $oDBReleases = new DBReleases\DBReleasesCommand();
  $oComando    = $oDBReleases;

  if ( $oInput->hasParameterOption(array('--help', '-h')) ) {

    error_reporting(false);
    $oComando = new \Symfony\Component\Console\Command\HelpCommand();
    $oComando->setCommand($oDBReleases);
    $oComando->run($oInput, $oOutput);
  } 

  $oComando->run($oInput, $oOutput);

} catch(Exception $oErro) {

  $oOutput = new \Symfony\Component\Console\Output\ConsoleOutput();
  $oOutput->writeln('<error>' . $oErro->getMessage() . '</error>');
}
