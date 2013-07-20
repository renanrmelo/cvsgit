<?php
namespace CVS;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class DiffCommand extends Command {

  /**
   * Configura comando
   *
   * @access public
   * @return void
   */
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

  /**
   * Executa comando diff
   *
   * @param OutputInterface $oInput
   * @param InputInterface $oOutput
   * @access public
   * @return void
   */
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
        $oOutput->writeln("<error>" . $this->getApplication()->getLastError() . "</error>");
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
      $oOutput->writeln("<error>" . $this->getApplication()->getLastError() . "</error>");
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
      $this->binario($sArquivo, $sArquivoPrimeiraVersao);
      return 1;
    }

    /**
     * Checkout - Segunda versao
     */
    exec(sprintf($sComandoCheckout, $nSegundaVersao), $aRetornoCheckout, $iStatusCheckout);

    if ( $iStatusCheckout > 0 || !file_exists("$sProjeto/$sArquivo") ) {

      $oOutput->writeln('<error>Erro ao executar checkout da versão "' . $nSegundaVersao . '"</error>');
      $oOutput->writeln("<error>" . $this->getApplication()->getLastError() . "</error>");
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
    $this->binario($sArquivoPrimeiraVersao, $sArquivoSegundaVersao);
  }

  /**
   * Executa binario passando 2 arquivos para fazer o diff
   *
   * @param strign $sArquivoPrimeiraVersao
   * @param strign $sArquivoSegundaVersao
   * @access private
   * @return void
   */
  private function binario($sArquivoPrimeiraVersao, $sArquivoSegundaVersao) {

    $aParametrosDiff = array();
    $sMascaraBinario = $this->getApplication()->getConfig('mascaraBinarioDiff');

    if ( empty($sMascaraBinario) ) {
      throw new \Exception("Mascara para binario do diff não encontrado, verifique arquivo de configuração");
    }

    $aParametrosMascara = \String::tokenize($sMascaraBinario);
    $sBinario           = array_shift($aParametrosMascara);

    if ( empty($sBinario) ) {
      throw new \Exception("Arquivo binário para diff não encontrado");
    }

    /**
     * Percorre os parametros e inclui arquivos para diff 
     */
    foreach ($aParametrosMascara as $sParametro) {

      if ( $sParametro == '[arquivo_1]' ) {
        $sParametro = $sArquivoPrimeiraVersao;
      }

      if ( $sParametro == '[arquivo_2]' ) {
        $sParametro = $sArquivoSegundaVersao;
      }
      
      $aParametrosDiff[] = $sParametro;
    }

    pcntl_exec($sBinario, $aParametrosDiff);
  }

}