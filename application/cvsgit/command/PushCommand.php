<?php
namespace CVS;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

require_once APPLICATION_DIR . 'cvsgit/model/PushModel.php';

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
    $this->tagArquivos();
  }

  public function tagArquivos() {

    $oOutput = $this->oOutput;
    $oInput = $this->oInput;
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
        throw new \Exception("Arquivo não encontrado na lista para commit: " . $this->getApplication()->clearPath($sArquivo));
      }

      $aArquivos[ $sArquivo ] = $aArquivosAdicionados[ $sArquivo ]; 
    }

    if ( empty($aArquivosParaCommit) ) {
      $aArquivos = $aArquivosAdicionados;
    }

    $this->oConfig = $this->getApplication()->getConfig();

    if ( empty($aArquivos) ) {

      throw new \LogicException("Nenhum arquivo para comitar");
      $oOutput->writeln("<error>Nenhum arquivo para comitar</error>");
      return;
    }

    $oTabela = new \Table();
    $oTabela->setHeaders(array('Arquivo', 'Tag', 'Mensagem', 'Tipo'));
    $aLinhas = array();
    $aComandos = array();
    $aMensagemErro = array();
    $iErros  = 0;

    $aTagSprint = $this->getTagsSprint();

    /**
     * Percorre arquivos validando configuracoes do commit
     */
    foreach ( $aArquivos as $oCommit ) {

      $sArquivo      = $this->getApplication()->clearPath($oCommit->sArquivo);
      $iTag          = $oCommit->iTag;
      $sMensagem     = $oCommit->sMensagem;
      $sTipoCompleto = $oCommit->sTipoCompleto;
      $sErro         = '<error>[x]</error>';

      /**
       * @todo, se arquivo nao existir usar cvs status para saber se deve deixar arquivo 
       */
      if ( !file_exists($oCommit->sArquivo) ) {

        $sArquivo = $sErro . ' ' . $sArquivo;
        $aMensagemErro[$sArquivo][] = "Arquivo não existe";
        $iErros++;
      }

      if ( empty($oCommit->sMensagem) ) {

        $aMensagemErro[$sArquivo][] = "Mensagem não informada";
        $sMensagem = $sErro;
        $iErros++;
      }
      /*
      if ( empty($oCommit->iTag) ) {

        $aMensagemErro[$sArquivo][] = "Tag não informada";
        $iTag = $sErro . $oCommit->iTag;
        $iErros++;
      }*/

      if ( !empty($oCommit->iTag) && !empty($aTagSprint) && !in_array($oCommit->iTag, $aTagSprint) ) {

        $aMensagemErro[$sArquivo][] = $oCommit->iTag . ": Tag não é do spring";

        if ( $this->oConfig->get('tag')->bloquearPush ) {

          $iTag = $sErro . ' ' .$oCommit->iTag;
          $iErros++;
        }
      }

      if ( empty($oCommit->sTipoAbreviado) || empty($oCommit->sTipoCompleto) ) {

        $aMensagemErro[$sArquivo][] = "Tipo de commit não informado";
        $sTipoCompleto = $sErro;
        $iErros++;
      }

      $oTabela->addRow(array($sArquivo, $iTag, $sMensagem, $sTipoCompleto));

      $aComandos[ $oCommit->sArquivo ][] = $oCommit;
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

    foreach($aComandos as $sArquivo => $aCommits) {

      foreach($aCommits as $oCommit) {

        $sMensagemAviso  = "";
        $sMensagemCommit = $oCommit->sMensagem;
        $sArquivoCommit  = $this->getApplication()->clearPath($oCommit->sArquivo);

        if ( !empty($aMensagemErro[$sArquivoCommit]) ) {
          $sMensagemAviso = '"' . implode(" | " , $aMensagemErro[$sArquivoCommit]). '"';
        }

        $oOutput->writeln("-- <comment>$sArquivoCommit:</comment> $sMensagemAviso");

        if ( $oCommit->sTipoAbreviado == 'ADD' ) {
          $oOutput->writeln("   " . $this->addArquivo($oCommit));
        }

        $oOutput->writeln('   ' . $this->commitArquivo($oCommit));
        $oOutput->writeln('   ' . $this->tagArquivo($oCommit));
        $oOutput->writeln('');
      }

    }

    $iTagRelease = $this->oConfig->get('tag')->release;

    if ( !empty($iTagRelease) ) {

      $oOutput->writeln("-- <comment>Tag release</comment>");
      $oOutput->writeln("   " . $iTagRelease);
      $oOutput->writeln("");
    }

    $oDialog   = $this->getHelperSet()->get('dialog');
    $sConfirma = $oDialog->ask($oOutput, 'Commitar?: (s/N): ');

    if ( strtoupper($sConfirma) != 'S' ) {
      exit;
    } 

    $oOutput->writeln('');
    $aArquivosCommitados = array();

    foreach($aComandos as $sArquivo => $aCommits) {

      foreach($aCommits as $oCommit) {

        $sArquivoCommit = $this->getApplication()->clearPath($oCommit->sArquivo);
        $sComandoAdd    = $this->addArquivo($oCommit)    . " 2> /tmp/cvsgit_last_error";
        $sComandoCommit = $this->commitArquivo($oCommit) . " 2> /tmp/cvsgit_last_error";
        $sComandoTag    = $this->tagArquivo($oCommit)    . " 2> /tmp/cvsgit_last_error";

        if ( $oCommit->sTipoAbreviado == 'ADD' ) {

          exec( $sComandoAdd, $aRetornoComandoAdd, $iStatusComandoAdd );

          if ( $iStatusComandoAdd > 0 ) {

            $oOutput->writeln("<error> - Erro ao adicionar arquivo: $sArquivoCommit</error>");
            continue;
          }
        }

        exec( $sComandoCommit, $aRetornoComandoCommit, $iStatusComandoCommit );

        if ( $iStatusComandoCommit > 0 ) {

          $oOutput->writeln("<error> - Erro ao commitar arquivo: $sArquivoCommit</error>");
          continue;
        }

        exec( $sComandoTag, $aRetornoComandoTag, $iStatusComandoTag );

        if ( $iStatusComandoTag > 0 ) {

          $oOutput->writeln("<error> - Erro ao por tag no arquivo: $sArquivoCommit</error>");
          continue;
        }
        
        $oOutput->writeln("<info> - Arquivo commitado: $sArquivoCommit</info>");
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

    $iTag = $oCommit->iTag; 

    if ( !empty($oCommit->iTagRelease) ) {
      $iTag = $oCommit->iTagRelease; 
    } 

    if ( empty($oCommit->iTagRelease) ) {

      $iTagRelease = $this->oConfig->get('tag')->release; 

      if ( !empty($iTagRelease) ) {
        $iTag = $iTagRelease;
      }
    }

    $sArquivoCommit  = $this->getApplication()->clearPath($oCommit->sArquivo);
    
    if ( empty($iTag) ) {
      return;
    }
    
    return \Encode::toUTF8("cvs tag -F T{$iTag} " . escapeshellarg($sArquivoCommit));
  }

  private function addArquivo($oCommit) {

    $sArquivoCommit  = $this->getApplication()->clearPath($oCommit->sArquivo);
    return "cvs add " . $sArquivoCommit;
  }

  private function commitArquivo($oCommit) {

    $sTagMsgCommit = " #$oCommit->iTag";

    if( empty( $oCommit->iTag ) ){
      $sTagMsgCommit = "";
    }
    
    $sMensagemCommit = "$oCommit->sTipoAbreviado: $oCommit->sMensagem ({$oCommit->sTipoCompleto}$sTagMsgCommit)";
    $sMensagemCommit = str_replace("'", '"', $sMensagemCommit);
    $sArquivoCommit  = $this->getApplication()->clearPath($oCommit->sArquivo);
    return \Encode::toUTF8("cvs commit -m '$sMensagemCommit' " . escapeshellarg($sArquivoCommit));
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
