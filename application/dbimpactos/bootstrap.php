<?php
require_once APPLICATION_DIR . 'dbimpactos/DBImpactosApplication.php';
require_once APPLICATION_DIR . 'dbimpactos/command/BuscarCommand.php';
require_once APPLICATION_DIR . 'dbimpactos/command/AtualizarCommand.php';

define('PATH', __DIR__ . '/');

try {

  /**
   * Instancia app e define arquivo de configração
   */
  $oDBImpactos = new DBImpactos\DBImpactosApplication();

  /**
   * Adiciona programas 
   */
  $oDBImpactos->addCommands(array(
    new DBImpactos\BuscarCommand(),
    new DBImpactos\AtualizarCommand()
  ));

  /**
   * Executa aplicacao 
   */
  $oDBImpactos->run();

} catch(Exception $oErro) {

  $oOutput = new \Symfony\Component\Console\Output\ConsoleOutput();
  $oOutput->writeln('<error>' . $oErro->getMessage() . '</error>');
}
