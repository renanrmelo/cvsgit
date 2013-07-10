<?php
namespace CVS;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Output\ConsoleOutput;

class CVSApplication extends Application {

  const VERSION = '1.0';

  public $sProjeto;
  public $sDiretorioObjetos;

  private $oOutput;

  public function __construct() {

    parent::__construct('CVS', CVSApplication::VERSION);

    /**
     * Configuracoes 
     * @todo - criar arquivo .cvsgitrc
     */
    $aConfiguracoes = require_once(__DIR__ . '/config.php');

    /**
     * Diretorio 
     * - Diretorio usado pelo programa, sera criado
     * - Objetos de commit, configuracoes e arquivos temporarios
     */
    $this->sDiretorioObjetos = $aConfiguracoes['sDiretorioObjetos'] . '/' . ltrim('.cvsgit/objects/', '/');

    /**
     * Nome do repositorio
     */
    if ( file_exists('CVS/Repository') ) {
      $this->sProjeto = trim(file_get_contents('CVS/Repository'));
    }

    $this->oOutput = new ConsoleOutput();
  }

  private function getProjeto() {

    if ( !file_exists($this->sDiretorioObjetos . 'Objects') ) {

      $this->oOutput->writeln(getcwd() . " não inicializado");
      exit(1);
    }

    $aProjetos = unserialize(file_get_contents($this->sDiretorioObjetos . 'Objects'));
    $sDiretorioAtual = getcwd();

    foreach ($aProjetos as $sProjeto) {

      if ( strpos($sDiretorioAtual, $sProjeto) !== false && strpos($sDiretorioAtual, $sProjeto) == 0 ) {
        return $sProjeto;
      }
    }

    $this->oOutput->writeln(getcwd() . " não inicializado");
    exit(1);
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

}
