<?php
namespace DBImpactos;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Exception, Shell, Banco, Tokenizer, Arquivo;

class BuscarCommand extends Command {

  private $aArquivosBuscar = array();
  private $aArquivosIgnorar = array();
  private $aMenusExibidos = array();


  /**
   * Configura o comando
   *
   * @access public
   * @return void
   */
  public function configure() {

    $this->setName('buscar');
    $this->setDescription('Busca impactos de um arquivo');
    $this->setHelp('Busca impactos de um arquivo');

    $this->addArgument('arquivos', InputArgument::IS_ARRAY, 'Arquivos para commit');

    $this->addOption('trace',    't', InputOption::VALUE_NONE, 'Exibe arquivos usados até chegar ao menu');
    // $this->addOption('requires', '', InputOption::VALUE_NONE, 'Exibe arquivos requiridos pelo arquivo');
    // $this->addOption('used', '', InputOption::VALUE_NONE, 'Exibe arquivos que usam o arquivo');
    // $this->addOption('log', '', InputOption::VALUE_NONE, 'Exibe log de erros ao buscar impactos do arquivo');
    $this->addOption('file-list', '', InputOption::VALUE_REQUIRED, 'Arquivo com lista de arquivos a serems pesquisados');
    $this->addOption('file-ignore', '', InputOption::VALUE_REQUIRED, 'Arquivo com lista de arquivos para ignorar');
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

    foreach ( $oInput->getOptions() as $sArgumento => $sValorArgumento ) {

      if ( empty($sValorArgumento) ) {
        continue;
      }

      switch ( $sArgumento ) {

        case 'file-list' :

          $sNomeArquivoLista = $oInput->getOption('file-list');

          if ( !file_exists($sNomeArquivoLista) ) {
            throw new Exception("Arquivo com lista não encontrado: $sNomeArquivoLista");
          }

          $aConteudoArquivoLista = file($sNomeArquivoLista);
          foreach ( $aConteudoArquivoLista as $sLinhaArquivoLista ) {

            $sArquivoLista = trim($sLinhaArquivoLista);

            if ( empty($sArquivoLista) ) {
              continue;
            }

            $this->aArquivosBuscar[] = $sArquivoLista;
          }

        break;

        case 'file-ignore' :

          $sNomeArquivoListaIgnorar = $oInput->getOption('file-ignore');

          if ( !file_exists($sNomeArquivoListaIgnorar) ) {
            throw new Exception("Arquivo com lista para ignorar não encontrado: $sNomeArquivoListaIgnorar");
          }

          $aConteudoArquivoListaIgnorar = file($sNomeArquivoListaIgnorar);
          foreach ( $aConteudoArquivoListaIgnorar as $sLinhaArquivoListaIgnorar ) {

            $sArquivoListaIgnorar = trim($sLinhaArquivoListaIgnorar);

            if ( empty($sArquivoListaIgnorar) ) {
              continue;
            }

            $this->aArquivosIgnorar[] = $sArquivoListaIgnorar;
          }

        break;
      }
    }

    foreach( $oInput->getArgument('arquivos') as $sArquivo ) {
      $this->aArquivosBuscar[] = $sArquivo;
    }

    foreach ( $this->aArquivosBuscar as $sArquivo ) {

      if ( in_array($sArquivo, $this->aArquivosIgnorar) ) {
        continue;
      }
      
      $this->buscar($sArquivo, $oInput, $oOutput);
    }

  }

  private function buscar($sArquivoBusca, $oInput, $oOutput) {

    require_once PATH . 'lib/Banco.php';
    require_once PATH . 'lib/Arquivo.php';

    $lExibirOrigemMenu = false;
    $lExibirLog        = false;
    $lRequires         = false;
    $lRequerido        = false;
    $lArquivo          = true;
    $lDistinctMenu     = false;

    try {	

      $sDiretorioProjeto = '/var/www/dbportal_prj/';

      foreach ( $oInput->getOptions() as $sArgumento => $sValorArgumento ) {

        if ( empty($sValorArgumento) ) {
          continue;
        }

        switch ( $sArgumento ) {

          case 'trace' :
            $lExibirOrigemMenu = true;
          break;
        }
      }

      $oBanco = new Banco(PATH . 'db/impactos.db');

      /**
       * @todo - pesquiser log separado, e percorrer com foreach, pq pode ter 2 menus mesmo arquivo 
       */
      $aDadosArquivo = $oBanco->selectAll("SELECT * FROM arquivo WHERE caminho LIKE '%$sArquivoBusca'");

      if ( empty($aDadosArquivo) ) {
        throw new Exception("Arquivo não encontrado: {$sArquivoBusca}");
      }

      $oDadosArquivo = $aDadosArquivo[0];
      $iArquivo = $oDadosArquivo->id;
      $sArqiuvo = $oDadosArquivo->caminho;

      $oArquivo    = new Arquivo($iArquivo, $this->aArquivosIgnorar);
      $aRequires   = $oArquivo->getRequires();
      $aRequerido  = $oArquivo->getRequerido();
      $aMenus      = $oArquivo->getMenus();
      $aOrigemMenu = $oArquivo->getOrigemMenu();
      $sLog        = $oArquivo->getLog();

      if ( $lArquivo ) {

        $oOutput->writeln("\n" . str_repeat("-", Shell::columns()));
        $oOutput->writeln("Arquivo pesquisado: $sArquivoBusca");
        $oOutput->writeln(str_repeat("-", Shell::columns()));
      }

      if ( $lRequires ) {

        if ( !empty($aRequires) ) {
          $oOutput->writeln("\nRequires:");
        }

        foreach ($oArquivo->getRequires() as $oDadosRequires) {

          $sCaminhoArquivo = Arquivo::clearPath($oDadosRequires->caminho, $sDiretorioProjeto);
          $oOutput->write("\n - {$sCaminhoArquivo} #{$oDadosRequires->linha}");
        }
      }

      if ( $lRequerido ) {

        if ( !empty($aRequerido) ) {
          $oOutput->writeln("\n\nRequerido em:");
        }

        foreach ($oArquivo->getRequerido() as $oDadosRequerido) {

          $sCaminhoArquivo = Arquivo::clearPath($oDadosRequerido->caminho, $sDiretorioProjeto);
          $oOutput->write("\n - {$sCaminhoArquivo} #{$oDadosRequerido->linha}");
        }
      }

      if ( !empty($aMenus) ) {
        $oOutput->writeln("\n\nMenus:");
      }

      foreach( $aMenus as $iMenu => $aArquivoMenu ) {

        if ( $lDistinctMenu ) {

          if ( in_array($iMenu, $this->aMenusExibidos) ) {
            continue;
          }

          $this->aMenusExibidos[] = $iMenu;
        }
        $oMenu = $oBanco->select("SELECT caminho FROM menu WHERE id = $iMenu");
        $oOutput->write("\n - $oMenu->caminho");

        if ( !$lExibirOrigemMenu ) {
          continue;
        }

        if ( !empty($aOrigemMenu[$iMenu]) ) {

          $iTotalOrigens = count($aOrigemMenu[$iMenu]);

          $sSeparador = " > ";

          $oOutput->write("\n   ");
          foreach ($aOrigemMenu[$iMenu] as $iIndice => $mOrigem ) {

            if ( is_scalar($mOrigem) ) {

              if ( $iIndice > 0 ) {
                $oOutput->write($sSeparador); 
              }

              $iArquivoOrigem = $mOrigem;

              $oMenu = $oBanco->select("SELECT caminho FROM arquivo WHERE id = $iArquivoOrigem");
              $oOutput->write(Arquivo::clearPath($oMenu->caminho));
              continue;
            }

            foreach ( $mOrigem as $iIndiceOrigem => $iArquivoOrigem ) {

              if ( $iIndiceOrigem > 0 ) {
                $oOutput->write($sSeparador); 
              }

              if ( $iIndice > 0 && $iIndiceOrigem == 0 && $iTotalOrigens > 1 ) {
                $oOutput->write("\n   ");
              }

              $oMenu = $oBanco->select("SELECT caminho FROM arquivo WHERE id = $iArquivoOrigem");
              $oOutput->write(Arquivo::clearPath($oMenu->caminho));
            }
          }

          $oOutput->write("\n\n");
        } else {

          foreach ( $aArquivoMenu as $iArquivoMenu ) {

            $oMenu = $oBanco->select("SELECT caminho FROM arquivo WHERE id = {$iArquivoMenu}");
            $oOutput->write("\n   " . Arquivo::clearPath($oMenu->caminho) . "\n");
          }

        }
      }

      if ( $lExibirLog && !empty($sLog) ) {

        $oOutput->write("\nLog de erros:\n\n");
        $oOutput->write($sLog);
        $oOutput->write("\n");
      }

      if ( $lArquivo ) {

        if ( !$lExibirOrigemMenu ) {
          $oOutput->write("\n");
        }
        $oOutput->writeln("\n" . str_repeat("-", Shell::columns()));
        $oOutput->write("\n\n");
      }

    } catch (Exception $oErro) {
      $oOutput->write("\n" . $oErro->getMessage() ."\n");
    }

  }

}
