<?php
namespace CVS;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Output\ConsoleOutput;

class CVSApplication extends Application {

  const VERSION = '1.0';

  public $sProjeto;
  public $sDiretorioObjetos;

  private $oOutput;
  private $oConfig;

  public function __construct(\Config $oConfig) {

    parent::__construct('CVS', CVSApplication::VERSION);

    /**
     * Nome do repositorio
     */
    if ( file_exists('CVS/Repository') ) {
      $this->sProjeto = trim(file_get_contents('CVS/Repository'));
    }

    $this->oOutput = new ConsoleOutput();

    $this->setConfig($oConfig);

    /**
     * Diretorio 
     * - Diretorio usado pelo programa, sera criado
     * - Objetos de commit, configuracoes e arquivos temporarios
     */
    $this->sDiretorioObjetos = rtrim($oConfig->get('diretorioArquivosPrograma'), '/') . '/' . ltrim('.cvsgit/objects/', '/');
  }

  public function getDiretorioObjetos() {
    return $this->sDiretorioObjetos;
  }

  public function getProjeto() {

    if ( !file_exists($this->sDiretorioObjetos . 'Objects') ) {
      throw new \Exception(getcwd() . " não inicializado, utilize o comando cvsgit init");
    }

    $aProjetos = unserialize(file_get_contents($this->sDiretorioObjetos . 'Objects'));
    $sDiretorioAtual = getcwd();

    foreach ($aProjetos as $sProjeto) {

      if ( strpos($sDiretorioAtual, $sProjeto) !== false && strpos($sDiretorioAtual, $sProjeto) == 0 ) {
        return $sProjeto;
      }
    }

    throw new \Exception(getcwd() . " não inicializado, utilize o comando cvsgit init");
  }

  public function getArquivos() {
    
    $sDiretorioProjeto = $this->getProjeto();
    $sDiretorioObjetos = $this->sDiretorioObjetos . md5($sDiretorioProjeto);

    if ( !file_exists($sDiretorioObjetos) ) {
      return array();
    }

    return unserialize(file_get_contents($sDiretorioObjetos));
  }

  /**
   * Salva arquivos
   *
   * @param Array $aArquivos
   * @access private
   * @return boolean
   */
  public function salvarArquivos(Array $aArquivos) {

    $sDiretorioProjeto = $this->getProjeto();
    $sDiretorioObjetos = $this->sDiretorioObjetos . md5($sDiretorioProjeto);

    return file_put_contents($sDiretorioObjetos, serialize($aArquivos));
  }

  /**
   * Remove arquivos
   *
   * @param Array $aArquivosRemover
   * @access public
   * @return boolean
   */
  public function removerArquivos(Array $aArquivosRemover) {

    $aArquivos = $this->getArquivos();

    foreach ( $aArquivosRemover as $sArquivoRemover ) {

      if ( !empty($aArquivos[$sArquivoRemover]) ) {
        unset($aArquivos[$sArquivoRemover]);
      }

      if ( !empty($aArquivos[getcwd() . '/' . $sArquivoRemover]) ) {
        unset($aArquivos[getcwd() . '/' . $sArquivoRemover]);
      } 
    }

    return $this->salvarArquivos($aArquivos);
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

  public function setConfig(\Config $oConfig) {
    $this->oConfig= $oConfig;
  }

  public function getConfig($sConfig = null) {
    return $this->oConfig->get($sConfig);
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
