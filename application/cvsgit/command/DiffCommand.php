<?php
namespace CVS;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Exception, String;

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
      'Exibe diferenças entre versões do arquivo' . PHP_EOL .
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

    $sArquivo = $oInput->getArgument('arquivo');

    if ( !file_exists($sArquivo) ) {
      throw new Exception("Arquivo não existe: $sArquivo");
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
      exec('cvs log -N ' . escapeshellarg($sArquivo) . ' 2> /tmp/cvsgit_last_error', $aRetornoComandoInformacoes, $iStatusComandoInformacoes);

      if ( $iStatusComandoInformacoes > 0 ) {

        throw new Exception(
          "Erro ao execurar cvs log -N " . escapeshellarg($sArquivo) . PHP_EOL . $this->getApplication()->getLastError(), $iStatusComandoInformacoes
        );
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

    $sProjeto             = $this->getApplication()->getModel()->getProjeto()->name;
    $sDiretorioTemporario = '/tmp/cvs-diff/';
    $sSeparador           = '__';
    $sComandoCheckout     = "cvs checkout -r %s $sProjeto/" . escapeshellarg($sArquivo) . " 2> /tmp/cvsgit_last_error";
    $sComandoMover        = "mv {$sProjeto}/" . escapeshellarg($sArquivo) . " {$sDiretorioTemporario}[%s]\ " . basename($sArquivo);
    $sComandoMoverProjeto = "cp -rf $sProjeto/ $sDiretorioTemporario && rm -rf $sProjeto/";

    /**
     * Cria diretorio temporario
     */
    if ( !is_dir($sDiretorioTemporario) && !mkdir($sDiretorioTemporario, 0777, true) ) {
      throw new Exception("Não foi possivel criar diretório temporario: $sDiretorioTemporario");
    }

    /**
     * Checkout - Primeira versao 
     */
    exec(sprintf($sComandoCheckout, $nPrimeiraVersao), $aRetornoCheckout, $iStatusCheckout);

    /**
     * Erro - Primeira versao 
     */
    if ( $iStatusCheckout > 0 ) {

      throw new Exception(
        'Erro ao executar checkout da versão "' . $nPrimeiraVersao . '"' . PHP_EOL . $this->getApplication()->getLastError(),
        $iStatusCheckout
      );
    }

    /**
     * Mover primeria versao
     * - mover arquivo da primeira versao para diretorio temporario
     */
    exec(sprintf($sComandoMover . ' 2> /tmp/cvsgit_last_error', $nPrimeiraVersao), $aRetornoMover, $iStatusMover);

    if ( $iStatusMover > 0 ) {
      throw new Exception('Erro ao executar: ' . sprintf($sComandoMover, $nPrimeiraVersao), $iStatusMover);
    }

    $sArquivoPrimeiraVersao = $sDiretorioTemporario . "[" . $nPrimeiraVersao ."] " . basename($sArquivo);

    /**
     * Vim diff - segunda versao nao inforamada
     */
    if ( empty($nSegundaVersao) ) {

      exec('diff ' . escapeshellarg($sArquivo) . ' ' . escapeshellarg($sArquivoPrimeiraVersao) . ' > /tmp/cvsgit_diff');
      $sDiffPrimeiraVersao = trim(file_get_contents('/tmp/cvsgit_diff'));

      if ( empty($sDiffPrimeiraVersao) ) {

        exec($sComandoMoverProjeto);
        throw new Exception('Nenhuma diferença com a versão ' . $nPrimeiraVersao);
      }

      exec($sComandoMoverProjeto);
      $this->binario($sArquivo, $sArquivoPrimeiraVersao);
      return 1;
    }

    /**
     * Checkout - Segunda versao
     */
    exec(sprintf($sComandoCheckout, $nSegundaVersao), $aRetornoCheckout, $iStatusCheckout);

    if ( $iStatusCheckout > 0 ) {

      exec($sComandoMoverProjeto);
      throw new Exception(
        'Erro ao executar checkout da versão "' . $nSegundaVersao . '"' . PHP_EOL . $this->getApplication()->getLastError()
      );
    }

    /**
     * Mover segunda versao
     * - mover arquivo da primeira versao para diretorio temporario
     */
    exec(sprintf($sComandoMover . ' 2> /tmp/cvsgit_last_error', $nSegundaVersao), $aRetornoMover, $iStatusMover);

    if ( $iStatusMover > 0 ) {
      throw new Exception('Erro ao executar: ' . sprintf($sComandoMover, $nSegundaVersao));
    }

    $sArquivoSegundaVersao = $sDiretorioTemporario . "[" . $nSegundaVersao . "] " . basename($sArquivo);

    exec('diff ' . escapeshellarg($sArquivoPrimeiraVersao) . ' ' . escapeshellarg($sArquivoSegundaVersao) . ' > /tmp/cvsgit_diff');
    $sDiffDuasVersoes = trim(file_get_contents('/tmp/cvsgit_diff'));

    if ( empty($sDiffDuasVersoes) ) {
      throw new Exception('Nenhuma diferença entre as versões ' . $nPrimeiraVersao . ' e ' . $nSegundaVersao);
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
      throw new Exception("Mascara para binario do diff não encontrado, verifique arquivo de configuração");
    }

    $aParametrosMascara = String::tokenize($sMascaraBinario);
    $sBinario           = array_shift($aParametrosMascara);

    if ( empty($sBinario) ) {
      throw new Exception("Arquivo binário para diff não encontrado");
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
