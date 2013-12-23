<?php
namespace DBImpactos;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Exception, Banco, DBTokenizer, Arquivo;

class AtualizarCommand extends Command {

  private $oOutput;

  /**
   * Configura o comando
   *
   * @access public
   * @return void
   */
  public function configure() {

    $this->setName('atualizar');
    $this->setDescription('Atualizar banco de dados do projeto');
    $this->setHelp('Atualizar banco de dados do projeto');

    $this->addOption('project', 'p', InputOption::VALUE_REQUIRED, 'Diretório com arquivos do dbportal_prj');
    $this->addOption('clear', 'c', InputOption::VALUE_NONE, 'Limpa base');
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

    $this->oOutput = $oOutput;

    require_once PATH . 'lib/Arquivo.php';
    require_once PATH . 'lib/Banco.php';
    require_once PATH . 'lib/dbportal.php';
    require_once PATH . 'lib/DBTokenizer.php';

    $sDiretorioProjeto = $oInput->getOption('project');

    if ( empty($sDiretorioProjeto) ) {
      $sDiretorioProjeto = '/var/www/dbportal_prj/';
    }

    try {	

      $sArquivoBanco =  PATH . 'db/impactos.db'; 
      $lClear = false;

      foreach ( $oInput->getOptions() as $sArgumento => $sValorArgumento ) {

        if ( empty($sValorArgumento) ) {
          continue;
        }

        switch ( $sArgumento ) {

          case 'clear' :
            $lClear = true;
          break;
        }
      }

      if ( $lClear ) {

        if ( file_exists($sArquivoBanco) && !unlink($sArquivoBanco)) {
          throw new Exception("Não foi possivel remover arquivo: $sArquivoBanco");
        }
      }
     
      $oBanco = new Banco($sArquivoBanco);

      if ($lClear) {        
        $oBanco->executeFile(PATH . 'db/impactos.sql');
      }

      $oBanco->begin();	 

      $this->status("\n - [1/6] Gerando lista com menus do dicionário de dados");

      $oBanco->delete('menu');
      $aMenus = getMenus();

      foreach ( $aMenus as $oMenu ) {

        $oBanco->insert('menu', array(
          'id'       => $oMenu->id, 
          'caminho'  => $oMenu->caminho,
          'programa' => $oMenu->programa
        ));
      }

      $this->status(" - [2/6] Buscando arquivos do diretório: {$sDiretorioProjeto}");
      $aArquivos = Arquivo::getArquivos($sDiretorioProjeto);
      $iTotalArquivos = count($aArquivos);
      
      $this->status(" - [3/6] Verificando modificações de $iTotalArquivos arquivos.");

      foreach ( $aArquivos as $iIndice => $sArquivo ) {

        $sArquivo = $aArquivos[$iIndice];

        $this->status("   arquivo[ $iIndice/$iTotalArquivos ] ", true);
        $aDadosArquivo = $oBanco->selectAll("SELECT * FROM arquivo WHERE caminho = '$sArquivo'");

        if ( empty($aDadosArquivo) ) {
          
          $iTipo = Arquivo::buscarTipo($sArquivo);
          $iArquivo = $oBanco->insert(
            'arquivo', 
            array('caminho' => $sArquivo, 'modificado' => filemtime($sArquivo), 'tipo' => $iTipo)
          );

          $aArquivoID[$sArquivo] = $iArquivo;
          continue;
        }

        if ( count($aDadosArquivo) > 1 ) {
          throw new Exception("Dois arquivos com mesmo nome: $sArquivo");
        }

        $oDadosArquivo = $aDadosArquivo[0];

        if ( $oDadosArquivo->modificado == filemtime($sArquivo) ) {
          unset($aArquivos[$iIndice]);
        }

        $aArquivoID[$oDadosArquivo->caminho] = $oDadosArquivo->id;
      }

      sort($aArquivos);
      $iTotalArquivos = count($aArquivos);

      if ( $iTotalArquivos === 0 ) {

        $this->status(" - [4/6] Nenhum arquivo modificado");
        $this->status(" - [5/6] Nenhum arquivo incluido");
        $this->finalizar();
        return 0;
      }
      
      $this->status(" - [4/6] Incluindo $iTotalArquivos arquivos no banco");

      /**
       * Percorre todos os arquivos
       * - inserindo no banco 
       */
      foreach ( $aArquivos as $iIndice => $sArquivo ) {

        if ( empty($aArquivoID[$sArquivo]) ) { 

          $this->oOutput->writeln("\nID arquivo não encontrado: $sArquivo");
          continue;
        }

        $iArquivo = $aArquivoID[$sArquivo];

        $this->status("   arquivo[ $iIndice/$iTotalArquivos ] ", true);

        $oTokenizer = new DBTokenizer($sArquivo);
        $iTotalLinhas = $oTokenizer->getTotalLines();

        $oBanco->update('arquivo', array(
          'caminho' => $sArquivo, 'modificado' => filemtime($sArquivo), 'linhas' => $iTotalLinhas
        ), "id = {$iArquivo}");

        $oBanco->delete('metodo', "classe = (select id from classe where arquivo = $iArquivo)");
        $oBanco->delete('classe', "arquivo = $iArquivo");

        foreach ( $oTokenizer->getClasses() as $sClasse => $aDadosClasse ) {

          $aMetodos = $aDadosClasse['method'];
          $iClasse  = $oBanco->insert('classe', array('arquivo' => $iArquivo, 'nome' => $sClasse));

          foreach ( $aMetodos as $aDadosMetodo ) {

            $sMetodo = $aDadosMetodo['function'];
            $oBanco->insert('metodo', array('classe' => $iClasse, 'nome' => $sMetodo));
          }
        }

        $oBanco->delete('funcao', "arquivo = $iArquivo");
        $oBanco->delete('log', "arquivo = $iArquivo");

        foreach ( $oTokenizer->getFunctions() as $aDadosFuncao ) {

          $sFuncao = $aDadosFuncao['function'];
          $oBanco->insert('funcao', array('arquivo' => $iArquivo, 'nome' => $sFuncao));
        }

        $sMensagemLog = $oTokenizer->getLog();
        
        if ( !empty($sMensagemLog) ) {
          $oBanco->insert('log', array('arquivo' => $iArquivo, 'log' => $sMensagemLog));
        }

      }

      $this->status(" - [5/6] Buscando dependencias de $iTotalArquivos arquivos");

      foreach ( $aArquivos as $iIndice => $sArquivo ) {

        $this->status("   arquivo[ $iIndice/$iTotalArquivos ] ", true);

        if ( empty($aArquivoID[$sArquivo]) ) { 

          $this->oOutput->writeln("ID arquivo não encontrado: $sArquivo");
          continue;
        }

        $iArquivo         = $aArquivoID[$sArquivo];
        $oTokenizer       = new DBTokenizer($sArquivo);
        $aArquivosRequire = $oTokenizer->getRequires();
        $sMensagemLog     = "";

        foreach ($aArquivosRequire as $aDadosArquivoRequire ) {

          $sArquivoRequire = $aDadosArquivoRequire['file'];
          $iLinhaRequire = $aDadosArquivoRequire['line'];

          if ( empty($aArquivoID[$sArquivoRequire]) ) { 

            $sMensagemLog .= "\n ----------------------------------------------------------------------------------------------------\n";
            $sMensagemLog .= " - ID arquivo de require não encontrado: '" . $sArquivoRequire . "'";
            $sMensagemLog .= "\n  Usado em: $sArquivo: " . $iLinhaRequire;
            $sMensagemLog .= "\n ----------------------------------------------------------------------------------------------------\n";
            continue;
          }

          $iArquivoRequire = $aArquivoID[$sArquivoRequire]; 

          $oBanco->insert('require', array(
            'arquivo' => $iArquivo, 'arquivo_require' => $iArquivoRequire, 'linha' => $iLinhaRequire, 
          ));
        }

        foreach ( $oTokenizer->getDeclaring() as $aDadosDeclaracao ) {

          $sClasse = $aDadosDeclaracao['class'];
          $iLinhaDeclaracao = $aDadosDeclaracao['line'];

          $aArquivoClasse = $oBanco->selectAll("
            SELECT arquivo.caminho 
              FROM classe inner join arquivo on arquivo.id = classe.arquivo 
             WHERE classe.nome like '$sClasse'
          ");

          if ( empty($aArquivoClasse) ) {

            $sMensagemLog .= "\n ----------------------------------------------------------------------------------------------------\n";
            $sMensagemLog .= " - $sArquivo:\n   Classe não encontrada: $sClasse";
            $sMensagemLog .= "\n ----------------------------------------------------------------------------------------------------\n";
            continue;
          }

          if ( count($aArquivoClasse) > 1 ) {
            
            $sArquivoClasse = null;

            foreach ( $aArquivoClasse as $oArquivoClasseDupla ) {

              foreach ( $aArquivosRequire as $aDadosRequire ) {

                if ( $oArquivoClasseDupla->caminho == $aDadosRequire['file'] ) {

                  $sArquivoClasse = $oArquivoClasseDupla->caminho; 
                  break;
                } 
              }
            }

            if ( empty($sArquivoClasse) ) {
              $sArquivoClasse = db_autoload($sClasse);
            }

            if ( empty($sArquivoClasse) ) {

              $sMensagemLog .= "\n ----------------------------------------------------------------------------------------------------\n";
              $sMensagemLog .= " - $sArquivo:\n   Mais de um arquvo encontrado para a classe: $sClasse\n   ";
              $sMensagemLog .= "\n   aArquivoClasse:\n   " . print_r($aArquivoClasse, true);
              $sMensagemLog .= "\n   aArquivosRequire:\n   " . print_r($aArquivosRequire, true);
              $sMensagemLog .= "\n ----------------------------------------------------------------------------------------------------\n";
              continue;
            }

          } else {
            $sArquivoClasse = $aArquivoClasse[0]->caminho;
          }
          
          $iArquivoDeclaracao = $aArquivoID[$sArquivoClasse]; 

          $oBanco->insert('require', array(
            'arquivo' => $iArquivo, 'arquivo_require' => $iArquivoDeclaracao, 'linha' => $iLinhaDeclaracao, 
          ));
        }

        if ( !empty($sMensagemLog) ) {
          $oBanco->insert('log', array('arquivo' => $iArquivo, 'log' => $sMensagemLog));
        }
      }	

      $this->finalizar();
        
    } catch (Exception $oErro) {
      
      if ( !empty($oBanco) ) {
        $oBanco->rollback();
      }

      throw new Exception($oErro->getMessage());      
    }

  }

  public function atualizarMenus() {

    $this->status(" - [6/6] Atualizando menus");

    $oBanco = Banco::getInstancia();
    $oBanco->delete('menu_arquivo');
    $aArquivosSalvos = $oBanco->selectAll('
      select * from arquivo where tipo in (' . Arquivo::TIPO_PROGRAMA . ', ' . Arquivo::TIPO_MENU . ')
    ');

    foreach ( $aArquivosSalvos as $iIndice => $oArquivo ) {

      $iArquivo = $oArquivo->id;
      $sArquivo = basename($oArquivo->caminho);

      $aMenusArquivo = $oBanco->selectAll("select id from menu where programa like '$sArquivo%'");

      if ( !empty($aMenusArquivo) ) {

        $oBanco->update('arquivo', array('tipo' => Arquivo::TIPO_MENU), "id = {$iArquivo}");

        foreach ( $aMenusArquivo as $oMenuArquivo ) {
          $oBanco->insert('menu_arquivo', array('arquivo' => $iArquivo, "menu" => $oMenuArquivo->id));
        }
      }

    }
      
  }

  public function finalizar() {

    $this->atualizarMenus();

    $oBanco = Banco::getInstancia();
    $oBanco->commit();
    $oBanco->vacuum();

    $this->status(" - Processo concluido");
    $this->status("");
  }

  public function status($sMensagem, $lClear = false) {

    $iMemoriaLimite      = ini_get('memory_limit');
    $iMemoriaUsada       = round((memory_get_usage(true)/1024)/1024);
    $iMemoriaTotalUsada  = round((memory_get_peak_usage(true)/1024)/1024);
    $sInformacoesMemoria = " memoria[ usando: {$iMemoriaUsada}mb | pico: {$iMemoriaTotalUsada}mb" . ($iMemoriaLimite > 0 ? " | limite: {$iMemoriaLimite}mb" : "") . " ]";

    if ( $lClear ) {

      $this->oOutput->write("\r");
      $this->oOutput->write(str_pad("$sMensagem | $sInformacoesMemoria", $this->columns(), ' ', STR_PAD_RIGHT));
      return true;
    } 

    $this->oOutput->write("\r");
    $this->oOutput->writeln(str_pad("$sMensagem", $this->columns(), ' ', STR_PAD_RIGHT));
    return true;
  }

  public function columns() {

    static $columns = 0;

    if ( $columns === 0 ) {
      $columns = exec('/usr/bin/env tput cols');
    }

    return $columns;
  }

}
