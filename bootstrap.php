<?php
require_once 'vendor/autoload.php';

use CVS\CVSApplication as CVS;

define('CONFIG_DIR', getenv('HOME') . '/.cvsgit/');

try {

  /**
   * Instancia app e define arquivo de configração
   */
  $oCVS = new CVS();

  /**
   * Adiciona programas 
   */
  $oCVS->addCommands(array(
    new \CVS\HistoryCommand(),
    new \CVS\InitCommand(),
    new \CVS\PushCommand(),
    new \CVS\AddCommand(),
    new \CVS\TagCommand(),
    new \CVS\RemoveCommand(),
    new \CVS\StatusCommand(),
    new \CVS\PullCommand(),
    new \CVS\DiffCommand(),
    new \CVS\LogCommand(),
    new \CVS\ConfigCommand(),
    new \CVS\WhatChangedCommand(),
    new \CVS\AnnotateCommand(),
    new \CVS\DieCommand(),
    new \CVS\CheckoutCommand(),
  ));

  /**
   * Executa aplicacao 
   */
  $oCVS->run();

} catch(Exception $oErro) {

  $oOutput = new \Symfony\Component\Console\Output\ConsoleOutput();
  $oOutput->writeln("<error>\n [You Tá The Brinqueichon Uite Me, cara?]\n " . $oErro->getMessage() . "\n</error>");
}
