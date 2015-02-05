<?php
require_once APPLICATION_DIR . 'cvsgit/CVSApplication.php';
require_once APPLICATION_DIR . 'cvsgit/command/AddCommand.php';
require_once APPLICATION_DIR . 'cvsgit/command/TagCommand.php';
require_once APPLICATION_DIR . 'cvsgit/command/InitCommand.php';
require_once APPLICATION_DIR . 'cvsgit/command/PullCommand.php';
require_once APPLICATION_DIR . 'cvsgit/command/PushCommand.php';
require_once APPLICATION_DIR . 'cvsgit/command/RemoveCommand.php';
require_once APPLICATION_DIR . 'cvsgit/command/StatusCommand.php';
require_once APPLICATION_DIR . 'cvsgit/command/DiffCommand.php';
require_once APPLICATION_DIR . 'cvsgit/command/LogCommand.php';
require_once APPLICATION_DIR . 'cvsgit/command/ConfigCommand.php';
require_once APPLICATION_DIR . 'cvsgit/command/HistoryCommand.php';
require_once APPLICATION_DIR . 'cvsgit/command/WhatChangedCommand.php';
require_once APPLICATION_DIR . 'cvsgit/command/AnnotateCommand.php';
require_once APPLICATION_DIR . 'cvsgit/command/DieCommand.php';

require_once APPLICATION_DIR . 'cvsgit/model/CvsGitModel.php';
require_once APPLICATION_DIR . 'cvsgit/model/ArquivoModel.php';

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
  ));

  /**
   * Executa aplicacao 
   */
  $oCVS->run();

} catch(Exception $oErro) {

  $oOutput = new \Symfony\Component\Console\Output\ConsoleOutput();
  $oOutput->writeln("<error>\n [You Tá The Brinqueichon Uite Me, cara?]\n " . $oErro->getMessage() . "\n</error>");
}
