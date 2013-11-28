<?php
namespace CVS;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Exception, Table, Encode;

require_once APPLICATION_DIR . 'cvsgit/model/PushModel.php';
require_once APPLICATION_DIR . 'cvsgit/model/Arquivo.php';

class PushCommand extends Command {

  private $oInput;
  private $oOutput;
  private $oConfig;

  /**
   * Configura comando
   *
   * @access public
   * @return void
   */
  public function configure() {

    $this->setName('push');
    $this->setDescription('Envia modificações para repositório');
    $this->setHelp('Envia modificações para repositório');
    $this->addArgument('arquivos', InputArgument::IS_ARRAY, 'Arquivos para enviar para o repositorio');
    $this->addOption('message', 'm', InputOption::VALUE_REQUIRED, 'Mensagem de log do envio' );
  }

  /**
   * Executa comando
   *
   * @param Object $oInput
   * @param Object $oOutput
   * @access public
   * @return void
   */
  public function execute($oInput, $oOutput) {

    $this->oInput  = $oInput;
    $this->oOutput = $oOutput;
    $this->commitarArquivos();
  }

  public function commitarArquivos() {

    $oOutput = $this->oOutput;
    $oInput = $this->oInput;

    $aArquivos = array();
    $sTituloPush = $oInput->getOption('message');
    $oArquivoModel = new ArquivoModel();
    $aArquivosAdicionados = $oArquivoModel->getAdicionados();

    $aArquivosParaCommit = $oInput->getArgument('arquivos');

    foreach($aArquivosParaCommit as $sArquivo ) {

      $sArquivo = realpath($sArquivo);

      if ( empty($aArquivosAdicionados[ $sArquivo ]) ) {
        throw new Exception("Arquivo não encontrado na lista para commit: " . $this->getApplication()->clearPath($sArquivo));
      }

      $aArquivos[ $sArquivo ] = $aArquivosAdicionados[ $sArquivo ]; 
    }

    if ( empty($aArquivosParaCommit) ) {
      $aArquivos = $aArquivosAdicionados;
    }

    $this->oConfig = $this->getApplication()->getConfig();

    if ( empty($aArquivos) ) {
      throw new Exception("Nenhum arquivo para comitar");
    }

    $oTabela = new Table();
    $oTabela->setHeaders(array('Arquivo', 'Tag', 'Mensagem', 'Tipo'));
    $aLinhas = array();
    $aComandos = array();
    $aMensagemErro = array();
    $iErros  = 0;

    $aTagSprint = $this->getTagsSprint();

    /**
     * Validações
     * - Percorre arquivos validando configuracoes do commit
     */
    foreach ( $aArquivos as $oCommit ) {

      $sArquivo  = $this->getApplication()->clearPath($oCommit->getArquivo());
      $iTag      = $oCommit->getTagArquivo();
      $sMensagem = $oCommit->getMensagem();
      $sTipo     = $oCommit->getTipo();
      $sErro     = '<error>[x]</error>';

      /**
       * Valida arquivo
       * @todo, se arquivo nao existir usar cvs status para saber se deve deixar arquivo 
       */
      if ( !file_exists($oCommit->getArquivo()) ) {

        $sArquivo = $sErro . ' ' . $sArquivo;
        $aMensagemErro[$sArquivo][] = "Arquivo não existe";
        $iErros++;
      }

      /**
       * Valida mensagem 
       */
      if ( $oCommit->getComando() === Arquivo::COMANDO_COMMITAR_TAGGEAR || $oCommit->getComando() === Arquivo::COMANDO_COMMITAR ) {

        if ( empty($sMensagem) ) {

          $aMensagemErro[$sArquivo][] = "Mensagem não informada";
          $sMensagem = $sErro;
          $iErros++;
        }
      }     

      /**
       * Valida tag
       */
      if ( $oCommit->getComando() === Arquivo::COMANDO_COMMITAR_TAGGEAR || $oCommit->getComando() === Arquivo::COMANDO_ADICIONAR_TAG || $oCommit->getComando() === Arquivo::COMANDO_REMOVER_TAG ) {

        /**
         * Valida se tag é do SPRINT 
         */
        if ( !empty($iTag) && !empty($aTagSprint) && !in_array($iTag, $aTagSprint) ) {

          $aMensagemErro[$sArquivo][] = $iTag . ": Tag não é do spring";

          if ( $this->oConfig->get('tag')->bloquearPush ) {

            $iTag = $sErro . ' ' .$iTag;
            $iErros++;
          }
        }
      }

      /**
       * Tipo de commit não informado 
       */
      if ( $oCommit->getComando() === Arquivo::COMANDO_COMMITAR_TAGGEAR || $oCommit->getComando() === Arquivo::COMANDO_COMMITAR ) {

        if ( empty($sTipo) ) {

          $aMensagemErro[$sArquivo][] = "Tipo de commit não informado";
          $sTipo = $sErro;
          $iErros++;
        }
      }

      $oTabela->addRow(array($sArquivo, $iTag, $sMensagem, $sTipo));

      $aComandos[ $oCommit->getArquivo() ][] = $oCommit;
    }

    /**
     * Encontrou erros 
     */
    if ( $iErros > 0 ) {

      $oOutput->writeln("\n " . $iErros . " erro(s) encontrado(s):");

      foreach ( $aMensagemErro as $sArquivo => $aMensagemArquivo ) {

        $oOutput->writeln("\n -- " . $sArquivo);
        $oOutput->writeln("    " . implode("\n    ", $aMensagemArquivo));
      } 

      $oOutput->writeln($oTabela->render());
      return 1;
    }

    $oOutput->writeln('');

    /**
     * Exibe comandos que serão executados 
     */
    foreach($aComandos as $sArquivo => $aCommits) {

      foreach($aCommits as $oCommit) {

        $sMensagemAviso  = "";
        $sMensagemCommit = $oCommit->getMensagem();
        $sArquivoCommit  = $this->getApplication()->clearPath($oCommit->getArquivo());

        if ( !empty($aMensagemErro[$sArquivoCommit]) ) {
          $sMensagemAviso = '"' . implode(" | " , $aMensagemErro[$sArquivoCommit]). '"';
        }

        $oOutput->writeln("-- <comment>$sArquivoCommit:</comment> $sMensagemAviso");

        /**
         * Commitar e tagear 
         */
        if ( $oCommit->getComando() === Arquivo::COMANDO_COMMITAR_TAGGEAR || $oCommit->getComando() === Arquivo::COMANDO_COMMITAR ) {

          if ( $oCommit->getTipo() == 'ADD' ) {
            $oOutput->writeln("   " . $this->addArquivo($oCommit));
          }

          $oOutput->writeln('   ' . $this->commitArquivo($oCommit));
        }

        /**
         * tagear 
         */
        if ( $oCommit->getComando() === Arquivo::COMANDO_COMMITAR_TAGGEAR || $oCommit->getComando() === Arquivo::COMANDO_ADICIONAR_TAG || $oCommit->getComando() === Arquivo::COMANDO_REMOVER_TAG ) {
          $oOutput->writeln('   ' . $this->tagArquivo($oCommit));
        }

        $oOutput->writeln('');
      }

    }

    $oDialog   = $this->getHelperSet()->get('dialog');
    $sConfirma = $oDialog->ask($oOutput, 'Commitar?: (s/N): ');

    if ( strtoupper($sConfirma) != 'S' ) {
      exit;
    } 

    $oOutput->writeln('');
    $aArquivosCommitados = array();

    /**
     * Executa comandos CVS
     * - Percorre array com comandos do CVS 
     */
    foreach($aComandos as $sArquivo => $aCommits) {

      foreach($aCommits as $oCommit) {

        $sArquivoCommit = $this->getApplication()->clearPath($oCommit->getArquivo());

        $aComandosExecutados = array();

        if ( $oCommit->getComando() === Arquivo::COMANDO_COMMITAR_TAGGEAR || $oCommit->getComando() === Arquivo::COMANDO_COMMITAR ) {

          if ( $oCommit->getTipo() == 'ADD' ) {

            $sComandoAdd = $this->addArquivo($oCommit)    . " 2> /tmp/cvsgit_last_error";
            exec( $sComandoAdd, $aRetornoComandoAdd, $iStatusComandoAdd );

            if ( $iStatusComandoAdd > 0 ) {

              $this->getApplication()->displayError("Erro ao adicionar arquivo: {$sArquivoCommit}", $oOutput);
              continue;
            }

            $aComandosExecutados[] = 'Adicionado';
          }

          $sComandoCommit = $this->commitArquivo($oCommit) . " 2> /tmp/cvsgit_last_error";
          exec( $sComandoCommit, $aRetornoComandoCommit, $iStatusComandoCommit );

          if ( $iStatusComandoCommit > 0 ) {

            $this->getApplication()->displayError("Erro ao commitar arquivo: {$sArquivoCommit}", $oOutput);
            continue;
          }

          $aComandosExecutados[] = 'Commitado';
        }

        if ( $oCommit->getComando() === Arquivo::COMANDO_COMMITAR_TAGGEAR || $oCommit->getComando() === Arquivo::COMANDO_ADICIONAR_TAG || $oCommit->getComando() === Arquivo::COMANDO_REMOVER_TAG ) {

          $sComandoTag = $this->tagArquivo($oCommit)    . " 2> /tmp/cvsgit_last_error";
          exec( $sComandoTag, $aRetornoComandoTag, $iStatusComandoTag );

          if ( $iStatusComandoTag > 0 ) {

            $this->getApplication()->displayError("Erro ao por tag no arquivo: {$sArquivoCommit}", $oOutput);
            continue;
          }

          $aComandosExecutados[] = 'Taggeado';
        }
        
        $oOutput->writeln("<info> - Arquivo " . implode(', ', $aComandosExecutados). ": $sArquivoCommit</info>");
        $aArquivosCommitados[] = $oCommit;
      }

    }

    /**
     * - Salva arquivos commitados 
     * - Remove arquivos já commitados 
     */
    if ( !empty($aArquivosCommitados) ) {

      $oPushModel = new PushModel();
      $oPushModel->setTitulo($sTituloPush);
      $oPushModel->adicionar($aArquivosCommitados);
      $oPushModel->salvar();
    }

    $oOutput->writeln('');
  }

  private function tagArquivo($oCommit) {

    $iTag = $oCommit->getTagArquivo(); 

    if ( empty($iTag) ) {

      $iTagRelease = $this->oConfig->get('tag')->release; 

      if ( !empty($iTagRelease) ) {
        $iTag = $iTagRelease;
      }
    }

    $sArquivoCommit = $this->getApplication()->clearPath($oCommit->getArquivo());
    
    if ( empty($iTag) ) {
      return;
    }

    /**
     * Forçar se tag existir
     */
    $sComandoTag = '-F';

    if ( $oCommit->getComando() === Arquivo::COMANDO_REMOVER_TAG ) {
      $sComandoTag = '-d';
    }
    
    return Encode::toUTF8("cvs tag {$sComandoTag} T{$iTag} " . escapeshellarg($sArquivoCommit));
  }

  private function addArquivo($oCommit) {

    $sArquivoCommit  = $this->getApplication()->clearPath($oCommit->getArquivo());
    return "cvs add " . $sArquivoCommit;
  }

  private function commitArquivo($oCommit) {

    $sTagMensagem = $oCommit->getTagMensagem(); 
    $iTagArquivo = $oCommit->getTagArquivo();

    if( !empty($sTagMensagem) ){
      $sTagMensagem = " #" . $sTagMensagem;
    }
    
    $sTipoAbreviado = $oCommit->getTipo();
    $sTipoCompleto  = null;

    switch ($sTipoAbreviado) {

      case 'ADD' :
        $sTipoCompleto = 'added';
      break;

      /**
       * Commit para modificacoes do layout ou documentacao 
       */
      case 'STYLE' : 
        $sTipoCompleto = 'style';
      break;

      /**
       * Commit para correcao de erros 
       */ 
      case 'FIX' : 
        $sTipoCompleto = 'fixed'; 
      break;

      /**
       * Commit para melhorias 
       */
      case 'ENH' : 
        $sTipoCompleto = 'enhanced';
      break;

      default :
        throw new Exception("Tipo não abreviado de commit não encontrado para tipo: $sTipoAbreviado");
      break;
    }

    $sMensagemCommit = "$sTipoAbreviado: " . $oCommit->getMensagem() . " ({$sTipoCompleto}$sTagMensagem)";
    $sMensagemCommit = str_replace("'", '"', $sMensagemCommit);
    $sArquivoCommit  = $this->getApplication()->clearPath($oCommit->getArquivo());
    return Encode::toUTF8("cvs commit -m '$sMensagemCommit' " . escapeshellarg($sArquivoCommit));
  }

  private function getTagsSprint() {

    $tagsSprint = $this->oConfig->get('tag')->sprint;
    $aTagSprint = array();

    if ( is_array($tagsSprint) ) {
      $aTagSprint = $tagsSprint;
    }

    if ( is_object($tagsSprint) ) {

      foreach ( $tagsSprint as $sTag => $sDescricao ) {

        if ( !empty($sDescricao) ) {
          $aTagSprint[] = $sTag;
        }
      }
    }

    return $aTagSprint;
  }

}
