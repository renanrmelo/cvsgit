<?php
namespace CVS;

require_once APPLICATION_DIR . 'cvsgit/model/CvsGitModel.php';

class ArquivoModel extends CvsGitModel {

  /**
   * Retorna arquivos adicionados para commit
   *
   * @access public
   * @return array - lista de StdClass com configuração do commit
   */
  public function getAdicionados() {

    $aArquivos = array();
    $aArquivosSalvos = $this->getDataBase()->selectAll("SELECT * FROM add_files WHERE project_id = {$this->getProjeto()->id}");

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

  /**
   * Salva arquivos para commitar
   * - array de StdClass com configurações do commit
   *
   * @param Array $aArquivos
   * @access public
   * @return void
   */
  public function salvarAdicionados(Array $aArquivos) {

    $this->getDataBase()->begin();

    /**
     * Remove todos os arquivos daquele projeto antes de incluir 
     */
    $this->getDataBase()->delete('add_files', 'project_id = ' . $this->getProjeto()->id);

    /**
     * Salva no banco os arquivos com suas configurações de commit 
     */
    foreach ($aArquivos as $oArquivo) {

      $this->getDataBase()->insert('add_files', array(
        'project_id'  => $this->getProjeto()->id,
        'file'        => $oArquivo->sArquivo,
        'tag_message' => $oArquivo->iTag,
        'tag_file'    => $oArquivo->iTagRelease,
        'message'     => $oArquivo->sMensagem,
        'type_short'  => $oArquivo->sTipoAbreviado,
        'type_full'   => $oArquivo->sTipoCompleto
      ));
    } 

    $this->getDataBase()->commit();
  }

  /**
   * Remove arquivos da lista para commit
   *
   * @param Array $aArquivosRemover
   * @access public
   * @return void
   */
  public function removerArquivos(Array $aArquivosRemover) {

    $this->getDataBase()->begin();

    foreach ( $aArquivosRemover as $sArquivoRemover ) {
      $this->removerArquivo($sArquivoRemover);
    }

    $this->getDataBase()->commit();
  }

  /**
   * Remove arquivo da lista para commit
   *
   * @param string $sArquivo
   * @access public
   * @return boolean
   */
  public function removerArquivo($sArquivo) {
    return $this->getDataBase()->delete('add_files', "project_id = {$this->getProjeto()->id} and file = '$sArquivo'");
  }

  /**
   * Retorna os arquivos commitados
   * - cvsgit push
   *
   * @access public
   * @return array
   */
  public function getCommitados(\StdClass $oParametros = null) {

    $aArquivosCommitados = array();
    $sWhere = null;

    /**
     * Busca commites que contenham arquivos enviados por parametro 
     */
    if ( !empty($oParametros->aArquivos) ) {

      $sWhere .= " and ( ";

      foreach ( $oParametros->aArquivos as $iIndice => $sArquivo ) {

        if ( $iIndice  > 0 ) {
          $sWhere .= " or ";
        }

        $sWhere .= " name like '%$sArquivo%' ";
      }

      $sWhere .= " ) ";
    }

    /**
     * Busca commites com tag 
     */
    if ( !empty($oParametros->iTag) ) {
      $sWhere .= " and tag like '%$oParametros->iTag%'";
    }

    /**
     * Busca commites por data 
     */
    if ( !empty($oParametros->sData) ) {

      $oParametros->sData = date('Y-m-d', strtotime(str_replace('/', '-', $oParametros->sData)));
      $sWhere .= " and date between '$oParametros->sData 00:00:00' and '$oParametros->sData 23:59:59' ";
    }

    /**
     * Busca commites contendo mensagem 
     */
    if ( !empty($oParametros->aMensagens) ) {

      $sWhere .= " and ( ";

      foreach ( $oParametros->aMensagens as $iIndice => $sMensagem ) {

        if ( $iIndice  > 0 ) {
          $sWhere .= " or ";
        }

        $sWhere .= " message like '%$sMensagem%' ";
      }

      $sWhere .= " ) ";
    }

    $sSqlEnvios = "
      SELECT pull.id, title, date
        FROM pull
             INNER JOIN pull_files on pull_files.pull_id = pull.id
       WHERE pull.project_id = {$this->getProjeto()->id} $sWhere
    ";

    $aEnvios = $this->getDataBase()->selectAll($sSqlEnvios);

    foreach ( $aEnvios  as $oEnvio ) {

      $sSqlArquivos = "
        SELECT name, type, tag, message
          FROM pull_files
         WHERE pull_id = {$oEnvio->id}
      ";

      $oArquivosCommitados = new \StdClass();
      $oArquivosCommitados->title = $oEnvio->title;
      $oArquivosCommitados->date  = $oEnvio->date;
      $oArquivosCommitados->aArquivos = array();

      $aDadosArquivosCommitados = $this->getDataBase()->selectAll($sSqlArquivos);

      foreach ( $aDadosArquivosCommitados as $oDadosCommit ) {

        $oCommit = new \StdClass();
        $oCommit->name = $oDadosCommit->name;
        $oCommit->type = $oDadosCommit->type;
        $oCommit->tag  = $oDadosCommit->tag;
        $oCommit->message = $oDadosCommit->message;;

        $oArquivosCommitados->aArquivos[] = $oCommit;
      }

      $aArquivosCommitados[ $oEnvio->id ] = $oArquivosCommitados;
    }

    return $aArquivosCommitados;
  }

}
