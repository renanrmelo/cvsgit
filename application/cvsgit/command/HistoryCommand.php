<?php
namespace CVS;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class HistoryCommand extends Command {

  private $sParametroData;
  private $oOutput;
  private $oInput;
  private $oModel;
  private $oFileDataBase;

  /**
   * Configura o comando
   *
   * @access public
   * @return void
   */
  public function configure() {

    $this->setName('history');
    $this->setDescription('Exibe histórico do repositorio');
    $this->setHelp('Exibe histórico do repositorio');

    $this->addArgument('arquivos', InputArgument::OPTIONAL | InputArgument::IS_ARRAY, 'Arquivo(s) para exibir histórico');

    $this->addOption('tag',     't', InputOption::VALUE_REQUIRED, 'Tag do commite');
    $this->addOption('date',    'd', InputOption::VALUE_REQUIRED, 'Data dos commites');
    $this->addOption('user',    'u', InputOption::VALUE_REQUIRED, 'Usuário, autor do commit');
    $this->addOption('message', 'm', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Mensagem de log do commit');
    $this->addOption('import',  '',  InputOption::VALUE_NONE,     'Importar historioco de alterações do CVS');
  }

  /**
   * Executa o comando
   *
   * @param Object $oInput
   * @param Object $oOutput
   * @access public
   * @return void
   */
  public function execute($oInput, $oOutput) {

    $lImportarHistorico = $oInput->getOption('import');

    $oParametros = new \StdClass();
    $oParametros->aArquivos  = $oInput->getArgument('arquivos');
    $oParametros->iTag       = $oInput->getOption('tag');
    $oParametros->aMensagens = $oInput->getOption('message');
    $oParametros->sUsuario   = $oInput->getOption('user');
    $oParametros->sData      = $oInput->getOption('date');

    $this->oOutput = $oOutput;
    $this->oInput = $oInput;
    $this->oModel = $this->getApplication()->getModel();
    $this->oFileDataBase = $this->oModel->getFileDataBase();

    try {

      if ( !empty($lImportarHistorico) ) {

        $this->importarHistorico();
        return true;
      }

      $aHistorico = $this->getHistorico($oParametros);

      if ( empty($aHistorico) ) {
        throw new \Exception("Histórico não encontrado.");
      }

      $oTabela = new \Table();
      $oTabela->setHeaders(array('Arquivo', 'Autor', 'Data', 'Hora', 'Versão', 'Tag', 'Mensagem'));

      foreach( $aHistorico as $oArquivo ) { 

        $sArquivo  = $this->getApplication()->clearPath($oArquivo->name);
        $sAutor    = $oArquivo->author;
        $sData     = date('d/m/Y', strtotime($oArquivo->date));
        $sHora     = date('H:i:s', strtotime($oArquivo->date));
        $sVersao   = $oArquivo->revision;
        $sTags     = implode(',', $oArquivo->tags);
        $sMensagem = \Encode::toUTF8($oArquivo->message);

        $oTabela->addRow(array($sArquivo, $sAutor, $sData, $sHora, $sVersao, $sTags, $sMensagem));
      }

      $sOutput = $oTabela->render();
      $iColunas  = array_sum($oTabela->getWidths()); 
      $iColunas += count($oTabela->getWidths()) * 2;
      $iColunas += count($oTabela->getWidths()) - 1 ;

      if ( $iColunas > \Shell::columns() ) {

        $this->getApplication()->less($sOutput);
        return;
      }

      $oOutput->writeln($sOutput);

    } catch(\Exception $oErro) {
      $oOutput->writeln('<error>' . $oErro->getMessage() . '</error>');
    }
  }

  /**
   * Retorna a data para importacao
   * - retorna null quando nao foi importado ainda
   * - retorna data, caso informado, do parametro --date
   *
   * @access public
   * @return string
   */
  public function getDataImportacao() {

    $sParametroData = $this->oInput->getOption('date');

    if ( !empty($sParametroData) ) {
      return $sParametroData;
    }

    $oModel = $this->oModel;
    $oFileDataBase = $this->oFileDataBase;

    $oDataUpdateHistorico = $oFileDataBase->select('select date from history');

    if ( empty($oDataUpdateHistorico) ) {

      $oFileDataBase->insert('history', array('date' => date('Y-m-d')));
      return null;
    }

    $oFileDataBase->update('history', array('date' => date('Y-m-d')));

    $oDataUpdateHistorico = $oFileDataBase->select('select date from history');
    return $oDataUpdateHistorico->date;
  }

  /**
   * Importa historicos do CVS para usar em consultas locais
   *
   * @access public
   * @return boolean
   */
  public function importarHistorico() {

    $oModel = $this->oModel;
    $oFileDataBase = $this->oFileDataBase;

    $this->oOutput->write("\rBuscando arquivos do projeto.");

    /**
     * Busca arquivos para importar historico
     * - caso já foi feita importacao, atualiza apartir desta data 
     */
    $aArquivos = $this->getArquivos($this->getDataImportacao());

    $iTotalArquivos = count($aArquivos);
    $iArquivosImportados = 0;

    $oFileDataBase->begin();

    foreach( $aArquivos as $sArquivo ) {

      $iArquivosImportados++;
      $sArquivoBusca = getcwd() . '/' . $sArquivo;
      $sArquivoBusca = str_replace(' ', '\ ', $sArquivoBusca);

      /**
       * Retorna dados do historico por arquivo 
       */
      $aDadosLog = $this->getLogPorArquivo($sArquivoBusca);

      if ( empty($aDadosLog) ) {

        $this->oOutput->writeln("<error>Histórico não encontrado par arquivo: $sArquivoBusca</error>");
        continue;
      }

      $this->oOutput->write(
        "\r" . str_repeat(' ', \Shell::columns()) .
        "\r[$iArquivosImportados/$iTotalArquivos] Processando arquivo: $sArquivo"
      );

      $aDadosHistoricoArquivoSalvo = $oFileDataBase->selectAll("
        SELECT id 
          FROM history_file 
         WHERE project_id = " . $oModel->getProjeto()->id . "
           AND name = '{$sArquivoBusca}' 
      ");

      if ( !empty($aDadosHistoricoArquivoSalvo) ) {

        foreach( $aDadosHistoricoArquivoSalvo as $oDadosHistoricoArquivoSalvo ) {

          $oFileDataBase->delete('history_file', 'id = ' . $oDadosHistoricoArquivoSalvo->id);
          $oFileDataBase->delete('history_file_tag', 'history_file_id = ' . $oDadosHistoricoArquivoSalvo->id);
        }
      }

      foreach ( $aDadosLog as $aDadosArquivo ) {

        foreach ( $aDadosArquivo as $oDadoArquivo ) {

          $data = str_replace('/', '-', $oDadoArquivo->sData . ' ' . $oDadoArquivo->sHora);
          $data = strtotime($data);
          $sData = date('Y-m-d H:s:i', $data);

          $iHistorico = $oFileDataBase->insert('history_file', array(
            'project_id' => $oModel->getProjeto()->id, 
            'name'       => $sArquivoBusca, 
            'revision'   => $oDadoArquivo->iVersao, 
            'message'    => $oDadoArquivo->sMensagem,
            'author'     => $oDadoArquivo->sAutor,
            'date'       => $sData 
          ));

          foreach ( $oDadoArquivo->aTags as $sTag ) {

            $oFileDataBase->insert('history_file_tag', array(
              'history_file_id' => $iHistorico,
              'tag' => $sTag 
            ));
          } 
        }

      }

    }

    $oFileDataBase->commit();
    $this->oOutput->write(
      "\r" . str_repeat(' ', \Shell::columns()) .
      "\r<info>Histórico de $iArquivosImportados arquivos importado.</info>\n"
    );
    return true;
  }

  public function getHistorico(\StdClass $oParametros = null) {

    $aArquivosHistorico = array();
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
     * Busca commites por usuario
     */
    if ( !empty($oParametros->sUsuario) ) {
      $sWhere .= " and author like '%$oParametros->sUsuario%'";
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

    $oModel = $this->oModel;
    $oProjeto = $oModel->getProjeto();
    $oFileDataBase = $oModel->getFileDataBase();

    $sSqlHistorico = "
        SELECT history_file.id, name, revision, message, author, date
          FROM history_file
               LEFT JOIN history_file_tag on history_file_tag.history_file_id = history_file.id
         WHERE project_id = {$oProjeto->id} $sWhere
      ORDER BY date 
    ";

    $aHistoricos = $oFileDataBase->selectAll($sSqlHistorico);

    foreach ( $aHistoricos as $oHistorico ) {

      $oArquivo = new \StdClass();
      $oArquivo->name     = $oHistorico->name;
      $oArquivo->revision = $oHistorico->revision;
      $oArquivo->message  = $oHistorico->message; 
      $oArquivo->date     = $oHistorico->date; 
      $oArquivo->author   = $oHistorico->author; 
      $oArquivo->tags     = array(); 

      $aTagsPorVersao = $oFileDataBase->selectAll("
        SELECT tag FROM history_file_tag WHERE history_file_id = '{$oHistorico->id}'
      ");

      foreach ( $aTagsPorVersao as $oTag ) {
        $oArquivo->tags[] = $oTag->tag;
      }

      $aArquivosHistorico[ $oHistorico->id ] = $oArquivo;
    }

    return $aArquivosHistorico;
  }

  private function getTagsPorVersao($sArquivo, $iParametroVersao = null) {

    /**
     * Lista somenta as tags
     */
    exec('cvs log -h ' . $sArquivo . ' 2> /tmp/cvsgit_last_error', $aRetornoComandoTags, $iStatusComandoTags);

    if ( $iStatusComandoTags > 0 ) {

      throw new \Exception(
        'Erro ao execurar cvs log -h ' . $sArquivo . PHP_EOL . $this->getApplication()->getLastError()
      );
    }

    $aTagsPorVersao = array();
    $iVersaoAtual = 0;
    $lInicioListaTags = false;

    foreach( $aRetornoComandoTags as $iIndiceTags => $sLinhaRetornoTag ) {

      if ( strpos($sLinhaRetornoTag, 'head:') !== false ) {

        $iVersaoAtual = trim(str_replace('head:', '', $sLinhaRetornoTag));
        continue;
      }

      if ( strpos($sLinhaRetornoTag, 'symbolic names:') !== false ) {

        $lInicioListaTags = true;
        continue;
      }

      if ( $lInicioListaTags ) {

        if ( strpos($sLinhaRetornoTag, 'keyword substitution') !== false ) {
          break;
        }

        if ( strpos($sLinhaRetornoTag, 'total revisions') !== false ) {
          break;
        }

        $aLinhaTag = explode(':', $sLinhaRetornoTag);
        $iVersao   = trim($aLinhaTag[1]);
        $sTag      = trim($aLinhaTag[0]);

        $aTagsPorVersao[$iVersao][] = $sTag;
      }
    }

    if ( empty($aTagsPorVersao[$iParametroVersao]) ) {
      return $aTagsPorVersao;
    }

    return $aTagsPorVersao[$iParametroVersao];
  }

  private function getArquivos($sDataUpdateHistorico = null) {

    if ( !empty($sDataUpdateHistorico) ) {
      $sDataUpdateHistorico = "-d'>=$sDataUpdateHistorico'";
    }

    /**
     * Lista informacoes do commit, sem as tags
     */
    exec('cvs log -S -N ' . $sDataUpdateHistorico . ' 2> /tmp/cvsgit_last_error', $aRetornoComandoInformacoes, $iStatusComandoInformacoes);

    if ( $iStatusComandoInformacoes > 1 ) {

      throw new \Exception(
        'Erro nº ' . $iStatusComandoInformacoes . ' - nao execurar cvs log -N ' . $sParametroArquivo . PHP_EOL .
        $this->getApplication()->getLastError() 
      );
    }

    $aArquivos = array();

    foreach ( $aRetornoComandoInformacoes as $iIndice => $sLinhaRetorno ) {

      if ( empty($sLinhaRetorno) ) {
        continue;
      }

      if ( strpos($sLinhaRetorno, 'Working file:') !== false ) {
        $aArquivos[] = trim(str_replace('Working file:', '', $sLinhaRetorno));
      }

    }

    return $aArquivos;
  }

  public function getLogPorArquivo($sArquivo) {

    if ( !file_exists($sArquivo) ) {
      $this->oOutput->writeln("<error>Arquivo não existe: $sArquivo</error>");
    } 

    $sArquivo = $this->getApplication()->clearPath($sArquivo);

    /**
     * Lista somenta as tags
     */
    exec('cvs log -h ' . $sArquivo . ' 2> /tmp/cvsgit_last_error', $aRetornoComandoTags, $iStatusComandoTags);

    if ( $iStatusComandoTags > 0 ) {

      throw new \Exception(
        'Erro ao execurar cvs log -h ' . $sArquivo . PHP_EOL . $this->getApplication()->getLastError() 
      );
    }
    
    $aTagsPorVersao = array();
    $iVersaoAtual = 0;
    $lInicioListaTags = false;

    foreach( $aRetornoComandoTags as $iIndiceTags => $sLinhaRetornoTag ) {

      if ( strpos($sLinhaRetornoTag, 'head:') !== false ) {

        $iVersaoAtual = trim(str_replace('head:', '', $sLinhaRetornoTag));
        continue;
      }

      if ( strpos($sLinhaRetornoTag, 'symbolic names:') !== false ) {

        $lInicioListaTags = true;
        continue;
      }

      if ( $lInicioListaTags ) {

        if ( strpos($sLinhaRetornoTag, 'keyword substitution') !== false ) {
          break;
        }

        if ( strpos($sLinhaRetornoTag, 'total revisions') !== false ) {
          break;
        }

        $aLinhaTag = explode(':', $sLinhaRetornoTag);
        $iVersao   = trim($aLinhaTag[1]);
        $sTag      = trim($aLinhaTag[0]);

        $aTagsPorVersao[$iVersao][] = $sTag;
      }
    }

    /**
     * Lista informacoes do commit, sem as tags
     */
    exec('cvs log -N ' . $sArquivo . ' 2> /tmp/cvsgit_last_error', $aRetornoComandoInformacoes, $iStatusComandoInformacoes);

    if ( $iStatusComandoInformacoes > 0 ) {

      throw new \Exception(
        'Erro ao execurar cvs log -N ' . $sArquivo . PHP_EOL .  $this->getApplication()->getLastError() 
      );
    }

    $iLinhaInformacaoCommit = 0;

    $aDadosLog = array();
    $iVersao   = '';
    $sAutor    = '';
    $sData     = '';
    $sData     = '';
    $sMensagem = '';
    $sTagsVersao = '';


    foreach ( $aRetornoComandoInformacoes as $iIndice => $sLinhaRetorno ) {

      if ( strpos($sLinhaRetorno, '------') !== false ) {
        continue;
      } 

      if ( $iLinhaInformacaoCommit == 1 && $iIndice > 11 ) {

        if ( !empty($aTagsPorVersao[$iVersao]) ) {
          $aTagsVersao = $aTagsPorVersao[$iVersao];
        }

        $oDadosLog = new \Stdclass();
        $oDadosLog->sArquivo = $sArquivo;
        $oDadosLog->iVersao   = $iVersao;
        $oDadosLog->sAutor    = $sAutor;
        $oDadosLog->sData     = $sData;
        $oDadosLog->sHora     = $sHora;
        $oDadosLog->sMensagem = $sMensagem;
        $oDadosLog->aTags     = $aTagsVersao;

        $aDadosLog[ $sArquivo ][] = $oDadosLog;

        $iVersao   = '';
        $sAutor    = '';
        $sData     = '';
        $sData     = '';
        $sMensagem = '';
        $aTagsVersao = array();
      }

      if ( $iLinhaInformacaoCommit > 0 ) {
        $iLinhaInformacaoCommit--;
      } 

      /**
       * Versao
       */
      if ( strpos($sLinhaRetorno, 'revision') !== false && strpos($sLinhaRetorno, 'revision') === 0 ) {
        $iLinhaInformacaoCommit = 3;
      } 

      /**
       * Versao
       */
      if ( $iLinhaInformacaoCommit == 3 ) {

        $iVersao = trim(str_replace('revision', '', $sLinhaRetorno));
        continue;
      }

      /**
       * Data e autor 
       */
      if ( $iLinhaInformacaoCommit == 2 ) {

        $sLinhaRetorno = strtr($sLinhaRetorno, array('date:' => '', 'author:' => ''));
        $aLinhaInformacoesCommit = explode(';', $sLinhaRetorno);
        $sLinhaData = array_shift($aLinhaInformacoesCommit);
        $aLinhaData = explode(' ', $sLinhaData);

        $sData  = implode('/', array_reverse(explode('-', $aLinhaData[1])));
        $sHora  = $aLinhaData[2];

        $sAutor = trim(array_shift($aLinhaInformacoesCommit));
        continue;
      } 

      /**
       * Mensagem 
       */
      if ( $iLinhaInformacaoCommit == 1 ) {
        $sMensagem = $sLinhaRetorno;
      }
    }

    return $aDadosLog;
  }

  private function getHistoricoCVS($oOutput) {

    $sComando = "cvs history -a -c";

    if ( !empty($this->sParametroData) ) {
      $sComando .= " -D $this->sParametroData";
    } 

    exec($sComando . ' 2> /tmp/cvsgit_last_error', $aRetorno, $iStatus);
   
    if ( $iStatus > 0 ) {
      throw new \Exception("Erro ao executar $sComando");
    }

    if ( !empty($aRetorno[0]) && $aRetorno[0] == 'No records selected.' ) {
      throw new \Exception("Histórico não encontrado");
    }

    $oTabela = new \Table();
    $oTabela->setHeaders(array('Arquivo', 'Tipo', 'Autor', 'Data', 'Hora', 'Versão'));

    foreach ( $aRetorno as $sLinha ) {

      $sLinha = preg_replace('/\s(?=\s)/', '', $sLinha);
      $aLinha = explode(" ", $sLinha);
      $sTipo = $aLinha[0];
      $sData = $aLinha[1];
      $sHora = $aLinha[2];
      $sAutor = $aLinha[4];
      $iVersao = $aLinha[5];

      $sArquivo = $aLinha[6];
      $sProjeto = $this->getApplication()->getModel()->getProjeto()->name; 

      if ( trim($aLinha[7]) != $sProjeto ) {
        $sArquivo = str_replace($sProjeto . '/', '' , $aLinha[7] . '/' . $aLinha[6]);
      }
      
      $oTabela->addRow(array($sArquivo, $sTipo, $sAutor, $sData, $sHora, $iVersao));
    }

    $sOutput = $oTabela->render();
    $iColunas  = array_sum($oTabela->getWidths()); 
    $iColunas += count($oTabela->getWidths()) * 2;
    $iColunas += count($oTabela->getWidths()) - 1 ;

    if ( $iColunas > \Shell::columns() ) {

      $this->getApplication()->less($sOutput);
      return;
    }

    $oOutput->writeln($sOutput);
  }

}
