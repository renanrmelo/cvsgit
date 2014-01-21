<?php
namespace DBImpactos;

require_once APPLICATION_DIR . 'dbimpactos/DBImpactosApplication.php';
require_once APPLICATION_DIR . 'dbimpactos/command/BuscarCommand.php';
require_once APPLICATION_DIR . 'dbimpactos/command/AtualizarCommand.php';

define('PATH', __DIR__ . '/');

try {

  ini_set('memory_limit', '500M');

  /**
   * Instancia app e define arquivo de configração
   */
  $oDBImpactos = new DBImpactosApplication();

  /**
   * Adiciona programas 
   */
  $oDBImpactos->addCommands(
    array(
      new BuscarCommand(),
      new AtualizarCommand()
    )
  );

  /**
   * Executa aplicacao 
   */
  $oDBImpactos->run();

} catch(Exception $oErro) {

  $oOutput = new \Symfony\Component\Console\Output\ConsoleOutput();
  $oOutput->writeln('<error>' . $oErro->getMessage() . '</error>');
}
