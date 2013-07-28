<?php
namespace CVS;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Output\ConsoleOutput;

class CVSApplication extends Application {

  const VERSION = '1.0';

  private $oConfig;
  private $oModel;

  public function __construct() {
    parent::__construct('CVS', CVSApplication::VERSION);
  }

  /**
   * Mostra path do arquivo apartir do diretorio atual
   *
   * @param string $sPath
   * @access private
   * @return string
   */
  public function clearPath($sPath) {
    return str_replace( getcwd() . '/', '', $sPath );
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

  public function getConfig($sConfig = null) {

    $sArquivo = CONFIG_DIR . $this->getModel()->getProjeto()->name . '_config.json';

    if ( !file_exists($sArquivo) ) {
      return null;
    }

    $oConfig = new \Config($sArquivo);

    if ( is_null($sConfig) ) {
      return $oConfig;
    }

    return $oConfig->get($sConfig);
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

  /**
   * Retorna o model da aplicacao
   *
   * @access public
   * @return CvsGitModel
   */
  public function getModel() {

    if ( empty($this->oModel) ) {

      if ( !file_exists(CONFIG_DIR . 'cvsgit.db') ) {
        throw new \Exception('Projeto ainda não inicializado, utilize o comando cvsgit init');
      }

      $oFileDataBase = new \FileDataBase(CONFIG_DIR . 'cvsgit.db');
      $this->oModel = new CvsGitModel($oFileDataBase);
    }

    return $this->oModel;
  }

}
