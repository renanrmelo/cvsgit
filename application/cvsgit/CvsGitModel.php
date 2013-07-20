<?php

class CvsGitModel {

  private $oFileDataBase;
  private $oProjeto;

  public function __construct(FileDataBase $oFileDataBase) {

    $this->oFileDataBase = $oFileDataBase;
    $this->oProjeto = $this->getProjeto();
  }

  public function getProjeto() {

    if ( !file_exists('CVS/Repository') ) {
      throw new \Exception('Diretório atual não é um repositorio CVS.');
    }

    $sDiretorioAtual = getcwd();
    $sRepositorio = trim(file_get_contents('CVS/Repository'));
    $aProjetos = $this->oFileDataBase->selectAll("select * from project where name = '$sRepositorio' or path = '$sDiretorioAtual'");

    foreach( $aProjetos as $oProjeto ) {

      /**
       * Inicio do diretorio atual contem projeto 
       */
      if ( strpos($sDiretorioAtual, $oProjeto->path) !== false && strpos($sDiretorioAtual, $oProjeto->path) == 0 ) {
        return $oProjeto;
      }

      /**
       * Repositorio 
       */
      if ( $oProjeto->name == $sRepositorio ) {
        return $oProjeto;
      }

      /**
       * Diretorio atual 
       */
      if ( $oProjeto->path == $sDiretorioAtual ) {
        return $oProjeto;
      }
    }   

    throw new \Exception(getcwd() . " não inicializado, utilize o comando cvsgit init");
  }

  public function getArquivos() {

    $aArquivos = array();
    $aArquivosSalvos = $this->oFileDataBase->selectAll("SELECT * FROM add_files WHERE project_id = {$this->oProjeto->id}");

    foreach( $aArquivosSalvos as $oDadosArquivo) {

      $oArquivo = new \StdClass();
      $oArquivo->sArquivo       = $oDadosArquivo->file;
      $oArquivo->iTag           = $oDadosArquivo->tag_message;
      $oArquivo->iTagRelease    = $oDadosArquivo->tag_file;
      $oArquivo->sMensagem      = $oDadosArquivo->message;
      $oArquivo->sTipoAbreviado = $oDadosArquivo->type_short;
      $oArquivo->sTipoCompleto  = $oDadosArquivo->type_full;

      $aArquivos[$oDadosArquivo->file] = $oArquivo;
    }

    return $aArquivos;
  } 

  public function salvarArquivos(Array $aArquivos) {

    $lTransacao = $this->oFileDataBase->inTransaction();

    /**
     * Nao inicia outra transacao 
     */
    if ( !$lTransacao ) {
      $this->oFileDataBase->begin();
    }

    $this->oFileDataBase->delete('add_files', 'project_id = ' . $this->oProjeto->id);

    foreach ($aArquivos as $oArquivo) {

      $this->oFileDataBase->insert('add_files', array(
        'project_id'  => $this->oProjeto->id,
        'file'        => $oArquivo->sArquivo,
        'tag_message' => $oArquivo->iTag,
        'tag_file'    => $oArquivo->iTagRelease,
        'message'     => $oArquivo->sMensagem,
        'type_short'  => $oArquivo->sTipoAbreviado,
        'type_full'   => $oArquivo->sTipoCompleto
      ));
    } 

    if ( !$lTransacao ) {
      $this->oFileDataBase->commit();
    }
  }

  public function removerArquivos(Array $aArquivosRemover) {

    $this->oFileDataBase->begin();

    foreach ( $aArquivosRemover as $sArquivoRemover ) {
      $this->removerArquivo($sArquivoRemover);
    }

    $this->oFileDataBase->commit();
  }

  public function removerArquivo($sArquivo) {

    return $this->oFileDataBase->delete(
      'add_files', 
      "project_id = {$this->oProjeto->id} and file = '$sArquivo'"
    );
  }

  public function push(Array $aArquivosCommitados, $sTituloPush = null) {

    $this->oFileDataBase->begin();

    /**
     * Push 
     */
    $iPull = $this->oFileDataBase->insert('pull', array(
      'project_id' => $this->oProjeto->id,
      'title'      => $sTituloPush,
      'date'       => date('Y-m-d H:i:s')
    ));

    /**
     * Arquivos do pull 
     */
    foreach ( $aArquivosCommitados as $oCommit ) {

      $this->oFileDataBase->insert('pull_files', array(
        'pull_id' => $iPull,
        'name'    => $oCommit->sArquivo,
        'type'    => $oCommit->sTipoAbreviado,
        'tag'     => $oCommit->iTagRelease,
        'message' => $oCommit->sMensagem
      ));

      $this->removerArquivo($oCommit->sArquivo);
    } 

    $this->oFileDataBase->commit();
  }

  public function getArquivosCommitados() {

    $aArquivosCommitados = array();

    $aDadosArquivosCommitados = $this->oFileDataBase->selectAll("
      SELECT pull.id, title, date, name, type, tag, message
        FROM pull
             INNER JOIN pull_files on pull_files.pull_id = pull.id
       WHERE pull.project_id = {$this->oProjeto->id}
    ");

    foreach ( $aDadosArquivosCommitados as $oDadosCommit ) {

      if ( empty($aArquivosCommitados[$oDadosCommit->id]) ) {

        $oArquivosCommitados = new StdClass();
        $oArquivosCommitados->title = $oDadosCommit->title;
        $oArquivosCommitados->date  = $oDadosCommit->date;
        $oArquivosCommitados->aArquivos = array();
        $aArquivosCommitados[ $oDadosCommit->id ] = $oArquivosCommitados;
      }

      $oCommit = new StdClass();
      $oCommit->name = $oDadosCommit->name;
      $oCommit->type = $oDadosCommit->type;
      $oCommit->tag  = $oDadosCommit->tag;
      $oCommit->message = $oDadosCommit->message;;

      $oArquivosCommitados->aArquivos[] = $oCommit;
      $aArquivosCommitados[ $oDadosCommit->id ] = $oArquivosCommitados;
    }

    return $aArquivosCommitados;
  }

}
