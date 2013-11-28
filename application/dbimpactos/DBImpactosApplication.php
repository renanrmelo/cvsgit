<?php
namespace DBImpactos;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Output\ConsoleOutput;
use Exception;

class DBImpactosApplication extends Application {

  const VERSION = '1.0';

  public function __construct() {
    parent::__construct('DBImpactos', DBImpactosApplication::VERSION);
  }

  /**
   * Retorna o ultimo erro dos comandos passados para o shell
   *
   * @access private
   * @return string
   */
  public function getLastError() {
    return trim(file_get_contents('/tmp/cvsgit_last_error'));
  }

  /**
   * Less
   * - Exibe saida para terminal com comando "less"
   *
   * @param string $sOutput
   * @access public
   * @return void
   */
  public function less($sOutput) {

    file_put_contents('/tmp/cvsgit_less_output', $sOutput);
    pcntl_exec('/bin/less', array('/tmp/cvsgit_less_output'));
  }

}
