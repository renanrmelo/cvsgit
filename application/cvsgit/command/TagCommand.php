<?php
namespace CVS;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Exception;

class TagCommand extends Command {

  /**
   * Configura comando
   *
   * @access public
   * @return void
   */
  public function configure() {

    $this->setName('tag');
    $this->setDescription('Lista, cria e remove tags');
    $this->addArgument('tag', InputArgument::REQUIRED, 'Tag do arquivo');
    $this->addArgument('arquivos', InputArgument::IS_ARRAY, 'Arquivos para alterar tags');
    $this->setHelp('Altera tag dos arquivos já adicionados para commit(comando cvsgit add)');
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

    $oArquivoModel = new ArquivoModel();
    $aArquivosAdicionados = $oArquivoModel->getAdicionados();

    $aArquivos = array();
    $aArquivosParametro = $oInput->getArgument('arquivos');

    foreach($aArquivosParametro as $sArquivo ) {

      $sArquivo = getcwd() . '/' . $sArquivo;

      if ( empty($aArquivosAdicionados[ $sArquivo ]) ) {
        throw new Exception("Arquivo não encontrado na lista para commit: " . $this->getApplication()->clearPath($sArquivo));
      }

      $aArquivos[ $sArquivo ] = $aArquivosAdicionados[ $sArquivo ]; 
    }

    if ( empty($aArquivos) ) {
      $aArquivos = $aArquivosAdicionados;
    }

    $iTag = ltrim(strtoupper($oInput->getArgument('tag')), 'T');

    $aArquivosTaggeados = $oArquivoModel->taggearAdicionados($aArquivos, $iTag);

    if ( !empty($aArquivosTaggeados) ) {

      foreach ( $aArquivosTaggeados as $sArquivo ) {
        $oOutput->writeln("<info>Arquivo taggeado: " . $this->getApplication()->clearPath($sArquivo) . "</info>");
      }
    }
  }

}
