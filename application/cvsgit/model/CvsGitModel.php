<?php
namespace CVS;

class CvsGitModel {

  protected static $oDataBase;
  protected static $oProjeto;

  public function __construct() {

    if ( !file_exists(CONFIG_DIR . 'cvsgit.db') ) {
      throw new \Exception('Projeto ainda não inicializado, utilize o comando cvsgit init');
    }

    if ( empty(self::$oDataBase) || empty(self::$oProjeto) ) {

      self::$oDataBase = new \FileDataBase(CONFIG_DIR . 'cvsgit.db');
      $this->buscarProjeto();
    }
  }

  public function getDataBase() {
    return self::$oDataBase;
  }

  public function getProjeto() {
    return self::$oProjeto;
  }

  public function buscarProjeto() {

    if ( !file_exists('CVS/Repository') ) {
      throw new Exception("Diretório atual não é um repositorio CVS");
    }

    $sDiretorioAtual = getcwd();
    $sRepositorio = trim(file_get_contents('CVS/Repository'));
    $aProjetos = self::$oDataBase->selectAll("select * from project where name = '$sRepositorio' or path = '$sDiretorioAtual'");

    foreach( $aProjetos as $oProjeto ) {

      /**
       * Repositorio 
       */
      if ( $oProjeto->name == $sRepositorio ) {

        self::$oProjeto = $oProjeto;
        return true;
      }

      /**
       * Diretorio atual 
       */
      if ( $oProjeto->path == $sDiretorioAtual ) {

        self::$oProjeto = $oProjeto;
        return true;
      }

      /**
       * Inicio do diretorio atual contem projeto 
       */
      if ( strpos($sDiretorioAtual, $oProjeto->path) !== false && strpos($sDiretorioAtual, $oProjeto->path) == 0 ) {

        self::$oProjeto = $oProjeto;
        return true;
      }
    }   

    throw new Exception("Diretório atual não inicializado, utilize o comando cvsgit init");
  }

}
