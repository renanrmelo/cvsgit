<?php
namespace CVS;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Output\ConsoleOutput;

class CVSApplication extends Application {

  const VERSION = '1.0';

  private $oOutput;
  private $oConfig;
  private $oDataBase;
  private $oModel;

  public function __construct(\Config $oConfig) {

    parent::__construct('CVS', CVSApplication::VERSION);

    $this->oOutput = new ConsoleOutput();
    $this->oFileDataBase = new \FileDataBase(APPLICATION_DIR . 'cvsgit/cvsgit.db');
    $this->oModel = new \CvsGitModel($this->oFileDataBase);
    $this->setConfig($oConfig);
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
   * @todo incluir last error no banco
   *
   * @access private
   * @return string
   */
  public function getLastError() {
    return trim(file_get_contents('/tmp/cvsgit_last_error'));
  }

  public function setConfig(\Config $oConfig) {
    $this->oConfig= $oConfig;
  }

  public function getConfig($sConfig = null) {
    return $this->oConfig->get($sConfig);
  }

  /**
   * @todo - usar UM arquivo de config somente
   */
  public function getConfigProjeto($sConfig = null) {

    $sArquivo = getenv('HOME') . '/.' . $this->getModel()->getProjeto()->name . '_config.json';

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
   * Retorna o banco de dados
   *
   * @access public
   * @return FileDataBase
   */
  public function getFileDataBase() {
    return $this->oFileDataBase;
  }

  public function getModel() {
    return $this->oModel;
  }

}
