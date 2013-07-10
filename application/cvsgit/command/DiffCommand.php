<?php
namespace CVS;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class DiffCommand extends Command {

  public function configure() {

    $this->setName('diff');
    $this->setDescription('Exibe diferenças entre versões do arquivo');
    $this->setHelp(
      'Exibe diferenças entre verões do arquivo' . PHP_EOL .
      'Caso nenhuma versão for informada, compara versão local com ultima do repositorio'
    );
    $this->addArgument('arquivo', InputArgument::REQUIRED, 'Arquivo para comparação');
    $this->addArgument('primeira_versao', InputArgument::OPTIONAL, 'Primeira versão para comparação');
    $this->addArgument('segunda_versao', InputArgument::OPTIONAL, 'Segunda versão para comparação');
  }

  public function execute($oInput, $oOutput) {

    if ( empty($this->getApplication()->sProjeto) &&  !file_exists('CVS/Repository') ) {

      $oOutput->writeln('<error>Diretório atual não é um repositório CVS</error>');
      return 1;
    }

    $sArquivo = $oInput->getArgument('arquivo');

    if ( !file_exists($sArquivo) ) {

      $oOutput->writeln("<error>Arquivo não existe: $sArquivo</error>");
    }

    $nPrimeiraVersao = $oInput->getArgument('primeira_versao');
    $nSegundaVersao  = $oInput->getArgument('segunda_versao');

    /**
     * Nenhuma versao informada para usar diff 
     */
    if ( empty($nPrimeiraVersao) ) {

      /**
       * Lista informacoes do commit, sem as tags
       */
      exec('cvs log -N ' . $sArquivo . ' 2> /tmp/cvsgit_last_error', $aRetornoComandoInformacoes, $iStatusComandoInformacoes);

      if ( $iStatusComandoInformacoes > 0 ) {

        $oOutput->writeln("<error>Erro ao execurar cvs log -N $sArquivo</error>");
        return $iStatusComandoInformacoes;
      }

      $iVersaoAtual = null;

      foreach ( $aRetornoComandoInformacoes as $iIndice => $sLinhaRetorno ) {

        if ( strpos($sLinhaRetorno, 'head:') !== false ) {

          $iVersaoAtual = trim(str_replace('head:', '', $sLinhaRetorno));
          break;
        }
      }

      $nPrimeiraVersao = $iVersaoAtual;
    }

    $sDiffBinario         = '/usr/bin/vimdiff';
    $sProjeto             = $this->getApplication()->sProjeto;
    $sDiretorioTemporario = '/tmp/cvs-diff/';
    $sSeparador           = '__';
    $sComandoCheckout     = "cvs checkout -r %s $sProjeto/$sArquivo 2> /tmp/cvsgit_last_error";
    $sComandoMover        = "mv {$sProjeto}/{$sArquivo} {$sDiretorioTemporario}[%s]\ " . basename($sArquivo);
    $sComandoMoverProjeto = "cp -rf $sProjeto/ $sDiretorioTemporario && rm -rf $sProjeto/";

    /**
     * Cria diretorio temporario
     */
    if ( !is_dir($sDiretorioTemporario) && !mkdir($sDiretorioTemporario, 0777, true) ) {

      $oOutput->writeln("</error>Não foi possivel criar diretório temporario: $sDiretorioTemporario</error>");
      return 1;
    }

    /**
     * Checkout - Primeira versao 
     */
    exec(sprintf($sComandoCheckout, $nPrimeiraVersao), $aRetornoCheckout, $iStatusCheckout);

    /**
     * Erro - Primeira versao 
     */
    if ( $iStatusCheckout > 0 || !file_exists("$sProjeto/$sArquivo") ) {

      $oOutput->writeln('<error>Erro ao executar checkout da versão "' . $nPrimeiraVersao . '"</error>');
      return $iStatusCheckout;
    }

    /**
     * Mover primeria versao
     * - mover arquivo da primeira versao para diretorio temporario
     */
    exec(sprintf($sComandoMover . ' 2> /tmp/cvsgit_last_error', $nPrimeiraVersao), $aRetornoMover, $iStatusMover);

    if ( $iStatusMover > 0 ) {

      $oOutput->writeln('<error>Erro ao executar: ' . sprintf($sComandoMover, $nPrimeiraVersao) . '</error>');
      return $iStatusMover;
    }

    $sArquivoPrimeiraVersao = $sDiretorioTemporario . "[" . $nPrimeiraVersao ."] " . basename($sArquivo);

    /**
     * Vim diff - segunda versao nao inforamada
     */
    if ( empty($nSegundaVersao) ) {

      exec('diff ' . $sArquivo . ' ' . escapeshellarg($sArquivoPrimeiraVersao) . ' > /tmp/cvsgit_diff');
      $sDiffPrimeiraVersao = trim(file_get_contents('/tmp/cvsgit_diff'));

      if ( empty($sDiffPrimeiraVersao) ) {

        $oOutput->writeln('<error>Nenhuma diferença com a versão ' . $nPrimeiraVersao . '</error>');
        exec($sComandoMoverProjeto);
        return 1;
      }

      exec($sComandoMoverProjeto);
      pcntl_exec( $sDiffBinario, array($sArquivo, $sArquivoPrimeiraVersao) );
      return 1;
    }

    /**
     * Checkout - Segunda versao
     */
    exec(sprintf($sComandoCheckout, $nSegundaVersao), $aRetornoCheckout, $iStatusCheckout);

    if ( $iStatusCheckout > 0 || !file_exists("$sProjeto/$sArquivo") ) {

      $oOutput->writeln('</error>Erro ao executar checkout da versão "' . $nSegundaVersao . '"</error>');
      exec($sComandoMoverProjeto);
      return 1;
    }

    /**
     * Mover segunda versao
     * - mover arquivo da primeira versao para diretorio temporario
     */
    exec(sprintf($sComandoMover . ' 2> /tmp/cvsgit_last_error', $nSegundaVersao), $aRetornoMover, $iStatusMover);

    if ( $iStatusMover > 0 ) {

      $oOutput->writeln('<error>Erro ao executar: ' . sprintf($sComandoMover, $nSegundaVersao) . '</error>');
      return 1;
    }

    $sArquivoSegundaVersao = $sDiretorioTemporario . "[" . $nSegundaVersao . "] " . basename($sArquivo);

    exec('diff ' . escapeshellarg($sArquivoPrimeiraVersao) . ' ' . escapeshellarg($sArquivoSegundaVersao) . ' > /tmp/cvsgit_diff');
    $sDiffDuasVersoes = trim(file_get_contents('/tmp/cvsgit_diff'));

    if ( empty($sDiffDuasVersoes) ) {

      $oOutput->writeln('<error>Nenhuma diferença entre as versões ' . $nPrimeiraVersao . ' e ' . $nSegundaVersao . '</error>');
      return 1;
    }

    exec($sComandoMoverProjeto);
    pcntl_exec( $sDiffBinario, array($sArquivoPrimeiraVersao, $sArquivoSegundaVersao) );
  }

}
