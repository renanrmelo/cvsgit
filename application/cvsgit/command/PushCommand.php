<?php
namespace CVS;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class PushCommand extends Command {

  private $oConfig;

  public function configure() {

    $this->setName('push');
    $this->setDescription('Envia modificações para repositório');
    $this->setHelp('Envia modificações para repositório');
  }

  public function execute($oInput, $oOutput) {

    $aArquivos = $this->getApplication()->getArquivos();

    $sArquivo = $this->getApplication()->getDiretorioObjetos() . md5('config_' . $this->getApplication()->getProjeto());
    $this->oConfig = new \Config($sArquivo);

    if ( empty($aArquivos) ) {

      $oOutput->writeln("<error>Nenhum arquivo para comitar</error>");
      return;
    }

    $oTabela = new \Table();
    $oTabela->setHeaders(array('Arquivo', 'Tag', 'Mensagem', 'Tipo'));
    $aLinhas = array();
    $aComandos = array();
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
      $aMensagemErro = array();

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

      if ( empty($oCommit->iTag) ) {

        $aMensagemErro[$sArquivo][] = "Tag não informada";
        $iTag = $sErro . $oCommit->iTag;
        $iErros++;
      }

      if ( !empty($aTagSprint) && !in_array($oCommit->iTag, $aTagSprint) ) {

        $iTag = $sErro . ' ' .$oCommit->iTag;
        $aMensagemErro[$sArquivo][] = $oCommit->iTag . ": Tag não é do spring";

        if ( $this->oConfig->get('tag')->bloquearPush ) {
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
        $oOutput->writeln("    " . implode("\n", $aMensagemArquivo));
      } 

      $oOutput->writeln($oTabela->render());
      return 1;
    }

    $oOutput->writeln('');

    foreach($aComandos as $sArquivo => $aCommits) {

      foreach($aCommits as $oCommit) {

        $sMensagemCommit = $oCommit->sMensagem;
        $sArquivoCommit  = $this->getApplication()->clearPath($oCommit->sArquivo);

        $oOutput->writeln("-- <comment>$sArquivoCommit:</comment>");

        if ( $oCommit->sTipoAbreviado == 'ADD' ) {
          $oOutput->writeln("   " . $this->addArquivo($oCommit));
        }

        $oOutput->writeln('   ' . $this->commitArquivo($oCommit));
        $oOutput->writeln('   ' . $this->tagArquivo($oCommit));
        $oOutput->writeln('');
      }

    }

    if ( !empty($aMensagemErro) ) {

      foreach ( $aMensagemErro as $sArquivo => $aMensagemArquivo ) {

        $oOutput->writeln("-- <comment>" . $sArquivo . "</comment>");
        $oOutput->writeln("   " . implode("\n", $aMensagemArquivo));
      } 

      $oOutput->writeln("");
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
        $aArquivosCommitados[] = $oCommit->sArquivo;
      }

    }

    /**
     * Remove arquivos já commitados 
     */
    if ( !empty($aArquivosCommitados) ) {
      $this->getApplication()->removerArquivos($aArquivosCommitados);
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
    return \Encode::toUTF8("cvs tag -F T{$iTag} " . $sArquivoCommit);
  }

  private function addArquivo($oCommit) {

    $sArquivoCommit  = $this->getApplication()->clearPath($oCommit->sArquivo);
    return "cvs add " . $sArquivoCommit;
  }

  private function commitArquivo($oCommit) {

    $sMensagemCommit = $oCommit->sMensagem;
    $sMensagemCommit = "$oCommit->sTipoAbreviado: $sMensagemCommit ($oCommit->sTipoCompleto #$oCommit->iTag)";
    $sMensagemCommit = str_replace("'", '"', $sMensagemCommit);
    $sArquivoCommit  = $this->getApplication()->clearPath($oCommit->sArquivo);
    return \Encode::toUTF8("cvs commit -m '$sMensagemCommit' " . $sArquivoCommit);
  }

  private function getTagsSprint() {

    $tagsSprint = $this->oConfig->get('tag')->sprint;
    $aTagSprint = array();

    if ( is_array($tagsSprint) ) {
      $aTagSprint = tagsSprint;
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
