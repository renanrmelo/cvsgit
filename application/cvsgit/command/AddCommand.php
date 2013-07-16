<?php
namespace CVS;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class AddCommand extends Command {

  private $aArquivos;
  private $aArquivosAdicionar;
  private $oConfiguracaoCommit;
  private $oInput;
  private $oOutput;

  public function configure() {

    $this->setName('add');
    $this->setDescription('Adicinar arquivos para commitar');

    $this->addArgument('arquivos', InputArgument::IS_ARRAY, 'Arquivos para commit');

    $this->addOption('message',     'm', InputOption::VALUE_REQUIRED, 'Mensagem de log' );
    $this->addOption('tag',         't', InputOption::VALUE_REQUIRED, 'Tag' );
    $this->addOption('tag-release', 'r', InputOption::VALUE_REQUIRED, 'Tag da release' );

    $this->addOption('added',    'a', InputOption::VALUE_NONE, 'Tipo de commit: Adicionar arquivo' );
    $this->addOption('enhanced', 'e', InputOption::VALUE_NONE, 'Tipo de commit: Melhoria' );
    $this->addOption('fixed',    'f', InputOption::VALUE_NONE, 'Tipo de commit: Correção de bug' );
    $this->addOption('style',    's', InputOption::VALUE_NONE, 'Tipo de commit: Modificações estéticas no fonte' );

    $this->setHelp('Adiciona e configura arquivos para enviar ao repositório CVS.');
  }

  public function execute($oInput, $oOutput) {

    $this->oInput  = $oInput;
    $this->oOutput = $oOutput;

    $this->aArquivos = $this->getApplication()->getArquivos(); 
    $this->aArquivosAdicionar = array();

    $this->processaArgumentos();
    $this->processarArquivos();
  }

  public function processaArgumentos() {

    $aArquivos = array();

    $oParametros = new \StdClass();
    $oParametros->sMensagem      = null;
    $oParametros->iTag           = null;
    $oParametros->iTagRelease    = null;
    $oParametros->sTipoAbreviado = null;
    $oParametros->sTipoCompleto  = null;
    $oParametros->sArquivo       = null;

    foreach ( $this->oInput->getOptions() as $sArgumento => $sValorArgumento ) {

      if ( empty($sValorArgumento) ) {
        continue;
      }

      switch ( $sArgumento ) {

        /**
         * Mensagem do commit 
         */
        case 'message' :
          $oParametros->sMensagem = $this->oInput->getOption('message');
        break;

        /**
         * Tag do commit 
         */
        case 'tag' :
          $oParametros->iTag = ltrim( strtoupper($this->oInput->getOption('tag')), 'T' );
        break;

        /**
         * Tag do commit 
         */
        case 'tag-release' :
          $oParametros->iTagRelease = ltrim( strtoupper($this->oInput->getOption('tag-release')), 'T' );
        break;

        /**
         * Commit para adicionar fonte ou funcionalidade
         */
        case 'added' :

          $oParametros->sTipoAbreviado = 'ADD';
          $oParametros->sTipoCompleto  = 'added';

        break;

        /**
         * Commit para modificacoes do layout ou documentacao 
         */
        case 'style' : 

          $oParametros->sTipoAbreviado = 'STYLE';
          $oParametros->sTipoCompleto  = 'style';

        break;

        /**
         * Commit para correcao de erros 
         */ 
        case 'fixed' : 

          $oParametros->sTipoAbreviado = 'FIX';
          $oParametros->sTipoCompleto  = 'fixed';

        break;

        /**
         * Commit para melhorias 
         */
        case 'enhanced' : 

          $oParametros->sTipoAbreviado = 'ENH';
          $oParametros->sTipoCompleto  = 'enhanced';

        break;

      }
    }

    /**
     * Procura os arquivos para adicionar 
     */
    foreach( $this->oInput->getArgument('arquivos') as $sArquivo ) {

      if ( !file_exists($sArquivo) ) {
        continue;
      }

      if ( is_dir($sArquivo) ) {
        continue;
      }

      $this->aArquivosAdicionar[] = realpath($sArquivo);
    }

    $this->oConfiguracaoCommit = $oParametros;
  }

  /**
   * Processa argumentos nos arquivos
   * - se nao for passado nenhum arquivo nos parametros, atualiza todos
   *
   * @access private
   * @return void
   */
  private function processarArquivos() {

    $aArquivosParaConfigurar = array();
    $sMensagem = '<info>Arquivo %s: %s</info>';

    /**
     * Percorre os arquivos passados por parametro e adiciona
     * ao array de arquivos a serem persistidos
     */
    foreach ($this->aArquivosAdicionar as $sArquivoAdicionar) {

      /**
       * Arquivo ja adicionado
       */
      if ( array_key_exists($sArquivoAdicionar, $this->aArquivos) ) {

        $aArquivosParaConfigurar[] = $sArquivoAdicionar;
        continue;
      }

      $oConfiguracao = clone $this->oConfiguracaoCommit;
      $oConfiguracao->sArquivo = $sArquivoAdicionar;

      if ( empty($oConfiguracao->iTagRelease) ) {
        $oConfiguracao->iTagRelease = $this->getApplication()->getConfigProjeto('tag')->release;    
      }

      $this->aArquivos[ $sArquivoAdicionar ] = $oConfiguracao;
      $this->oOutput->writeln(sprintf($sMensagem, 'adicionado a lista', $this->getApplication()->clearPath($sArquivoAdicionar)));
    }

    /**
     * Não informou arquivo, entao atualiza todos os arquivos 
     * ja adicionados com parametros inforamdos 
     */
    if ( empty($this->aArquivosAdicionar) ) {
      $aArquivosParaConfigurar = array_keys($this->aArquivos);
    }

    $iArquivosAtualizados = 0;

    /**
     * Configura arquivos
     */
    foreach ($aArquivosParaConfigurar as $sArquivo ) {

      foreach ( $this->oConfiguracaoCommit as $sConfiguracao => $sValorConfiguracao ) {

        if ( empty($sValorConfiguracao) ) {
          continue;
        }

        $this->aArquivos[ $sArquivo ]->$sConfiguracao = $sValorConfiguracao;
        $iArquivosAtualizados++;
      }

      $this->aArquivos[ $sArquivo ]->sArquivo = $sArquivo;

      if ( $iArquivosAtualizados > 0 ) {
        $this->oOutput->writeln(sprintf($sMensagem, 'atualizado', $this->getApplication()->clearPath($sArquivo)));
      }
    }

    if ( $iArquivosAtualizados > 0 || !empty($this->aArquivosAdicionar) ) {
      $this->getApplication()->salvarArquivos( $this->aArquivos );
    }
  }

}
