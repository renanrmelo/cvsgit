<?php
namespace CVS;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class PushCommand extends Command {

  public function configure() {

    $this->setName('push');
    $this->setDescription('Envia modificações para repositório');
    $this->setHelp('Envia modificações para repositório');
  }

  public function execute($oInput, $oOutput) {

    $aArquivos = $this->getApplication()->getArquivos();

    if ( empty($aArquivos) ) {

      $oOutput->writeln("<error>Nenhum arquivo para comitar</error>");
      return;
    }

    $oTabela = new \Table();
    $oTabela->setHeaders(array('Arquivo', 'Tag', 'Mensagem', 'Tipo'));
    $aLinhas = array();
    $aComandos = array();
    $iErros  = 0;

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

        $sArquivo = $sErro . $sArquivo;
        $iErros++;
      }

      if ( empty($oCommit->sMensagem) ) {

        $sMensagem = $sErro;
        $iErros++;
      }

      if ( empty($oCommit->iTag) || mb_strlen($oCommit->iTag) < 4 ) {

        $iTag = $sErro;
        $iErros++;
      }

      if ( empty($oCommit->sTipoAbreviado) || empty($oCommit->sTipoCompleto) ) {

        $sTipoCompleto = $sErro;
        $iErros++;
      }

      $oTabela->addRow(array($sArquivo, $iTag, $sMensagem, $sTipoCompleto));

      $aComandos[ $oCommit->sArquivo ][] = $oCommit;
    }

    if ( $iErros > 0 ) {

      $oOutput->writeln("\n " . $iErros . " erro(s) encontrado(s):");
      $oOutput->writeln($oTabela->render());
      return;
    }

    $oOutput->writeln('');

    foreach($aComandos as $sArquivo => $aCommits) {

      foreach($aCommits as $oCommit) {

        $sMensagemCommit = $oCommit->sMensagem;
        $sArquivoCommit  = $this->getApplication()->clearPath($oCommit->sArquivo);

        $oOutput->writeln("-- <comment>$sArquivoCommit:</comment>");

        if ( $oCommit->sTipoAbreviado == 'ADD' ) {
          $oOutput->writeln("   " . "cvs add " . $sArquivoCommit);
        }

        $oOutput->writeln(\Encode::toUTF8("   cvs commit -m '$oCommit->sTipoAbreviado: $sMensagemCommit ($oCommit->sTipoCompleto #$oCommit->iTag)' " . $sArquivoCommit));
        $oOutput->writeln(\Encode::toUTF8("   cvs tag -F T{$oCommit->iTag} " . $sArquivoCommit));
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

    foreach($aComandos as $sArquivo => $aCommits) {

      foreach($aCommits as $oCommit) {

        $sMensagemCommit = $oCommit->sMensagem;
        $sMensagemCommit = "$oCommit->sTipoAbreviado: $sMensagemCommit ($oCommit->sTipoCompleto #$oCommit->iTag)";
        $sMensagemCommit = str_replace("'", '"', $sMensagemCommit);
        $sArquivoCommit  = $this->getApplication()->clearPath($oCommit->sArquivo);

        $sComandoAdd    = \Encode::toUTF8("cvs add " . $sArquivoCommit . " 2> /tmp/cvsgit_last_error");
        $sComandoCommit = \Encode::toUTF8("cvs commit -m '" . $sMensagemCommit . "' " . $sArquivoCommit . " 2> /tmp/cvsgit_last_error");
        $sComandoTag    = \Encode::toUTF8("cvs tag -F T{$oCommit->iTag} " . $sArquivoCommit . " 2> /tmp/cvsgit_last_error");

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

}
