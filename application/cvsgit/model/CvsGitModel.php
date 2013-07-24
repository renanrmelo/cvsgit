<?php

class CvsGitModel {

  private $oFileDataBase;
  private $oProjeto;

  public function __construct(FileDataBase $oFileDataBase) {

    $this->oFileDataBase = $oFileDataBase;
    $this->buscarProjeto();
  }

  public function getFileDataBase() {
    return $this->oFileDataBase;
  }

  public function getProjeto() {
    return $this->oProjeto;
  }

  public function buscarProjeto() {

    if ( !file_exists('CVS/Repository') ) {
      throw new Exception("Diretório atual não é um repositorio CVS");
    }

    $sDiretorioAtual = getcwd();
    $sRepositorio = trim(file_get_contents('CVS/Repository'));
    $aProjetos = $this->oFileDataBase->selectAll("select * from project where name = '$sRepositorio' or path = '$sDiretorioAtual'");

    foreach( $aProjetos as $oProjeto ) {

      /**
       * Repositorio 
       */
      if ( $oProjeto->name == $sRepositorio ) {

        $this->oProjeto = $oProjeto;
        return true;
      }

      /**
       * Diretorio atual 
       */
      if ( $oProjeto->path == $sDiretorioAtual ) {

        $this->oProjeto = $oProjeto;
        return true;
      }

      /**
       * Inicio do diretorio atual contem projeto 
       */
      if ( strpos($sDiretorioAtual, $oProjeto->path) !== false && strpos($sDiretorioAtual, $oProjeto->path) == 0 ) {

        $this->oProjeto = $oProjeto;
        return true;
      }
    }   

    throw new Exception("Diretório atual não inicializado, utilize o comando cvsgit init");
  }

  /**
   * Retorna arquivos adicionados para commit
   *
   * @access public
   * @return array - lista de StdClass com configuração do commit
   */
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

  /**
   * Salva arquivos para commitar
   * - aray de StdClass com configurações do commit
   *
   * @param Array $aArquivos
   * @access public
   * @return void
   */
  public function salvarArquivos(Array $aArquivos) {

    $this->oFileDataBase->begin();

    /**
     * Remove todos os arquivos daquele projeto antes de incluir 
     */
    $this->oFileDataBase->delete('add_files', 'project_id = ' . $this->oProjeto->id);

    /**
     * Salva no banco os arquivos com suas configurações de commit 
     */
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

    $this->oFileDataBase->commit();
  }

  /**
   * Remove arquivos da lista para commit
   *
   * @param Array $aArquivosRemover
   * @access public
   * @return void
   */
  public function removerArquivos(Array $aArquivosRemover) {

    $this->oFileDataBase->begin();

    foreach ( $aArquivosRemover as $sArquivoRemover ) {
      $this->removerArquivo($sArquivoRemover);
    }

    $this->oFileDataBase->commit();
  }

  /**
   * Remove arquivo da lista para commit
   *
   * @param string $sArquivo
   * @access public
   * @return boolean
   */
  public function removerArquivo($sArquivo) {
    return $this->oFileDataBase->delete('add_files', "project_id = {$this->oProjeto->id} and file = '$sArquivo'");
  }

  /**
   * Salva no banco as modificacoes do comando cvsgit push
   *
   * @param Array $aArquivosCommitados
   * @param string $sTituloPush
   * @access public
   * @return void
   */
  public function push(Array $aArquivosCommitados, $sTituloPush = null) {

    $this->oFileDataBase->begin();

    /**
     * Cria header do push 
     * @var integer $iPull - pk da tabela pull
     */
    $iPull = $this->oFileDataBase->insert('pull', array(
      'project_id' => $this->oProjeto->id,
      'title'      => $sTituloPush,
      'date'       => date('Y-m-d H:i:s')
    ));

    /**
     * Percorre array de arquivos commitados e salva no banco
     */
    foreach ( $aArquivosCommitados as $oCommit ) {

      $iTag = $oCommit->iTag;

      if ( !empty($oCommit->iTagRelease) ) {
        $iTag = $oCommit->iTagRelease;
      }

      $this->oFileDataBase->insert('pull_files', array(
        'pull_id' => $iPull,
        'name'    => $oCommit->sArquivo,
        'type'    => $oCommit->sTipoAbreviado,
        'tag'     => $iTag,
        'message' => $oCommit->sMensagem
      ));

      /**
       * Remove arqui da lista para commit 
       */
      $this->removerArquivo($oCommit->sArquivo);
    } 

    $this->oFileDataBase->commit();
  }

  /**
   * Retorna os arquivos commitados
   * - cvsgit push
   *
   * @access public
   * @return array
   */
  public function getArquivosCommitados(StdClass $oParametros = null) {

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
       WHERE pull.project_id = {$this->oProjeto->id} $sWhere
    ";

    $aEnvios = $this->oFileDataBase->selectAll($sSqlEnvios);

    foreach ( $aEnvios  as $oEnvio ) {

      $sSqlArquivos = "
        SELECT name, type, tag, message
          FROM pull_files
         WHERE pull_id = {$oEnvio->id}
      ";

      $oArquivosCommitados = new StdClass();
      $oArquivosCommitados->title = $oEnvio->title;
      $oArquivosCommitados->date  = $oEnvio->date;
      $oArquivosCommitados->aArquivos = array();

      $aDadosArquivosCommitados = $this->oFileDataBase->selectAll($sSqlArquivos);

      foreach ( $aDadosArquivosCommitados as $oDadosCommit ) {

        $oCommit = new StdClass();
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
