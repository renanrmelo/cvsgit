<?php
namespace CVS;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class ConfigCommand extends Command {

  private $sArquivoConfiguracoes; 

  /**
   * Caminho do editor
   * - usado para editar arquivo json de configuracoes
   * 
   * @var string
   * @access private
   */
  private $sCaminhoEditor = '/usr/bin/vim';

  /**
   * Parametro passados para o editor
   * 
   * @var array
   * @access private
   */
  private $aParametrosEditor = array('-c', 'set ft=javascript');

  /**
   * Configura o comando
   *
   * @access public
   * @return void
   */
  public function configure() {

    $this->setName('config');
    $this->setDescription('Exibe configurações da aplicação');
    $this->setHelp('Exibe configurações da aplicação');
    $this->addOption('edit', 'e', InputOption::VALUE_NONE, 'Editar configurações');
    $this->addOption('restart', 'r', InputOption::VALUE_NONE, 'Reiniciar configurações');
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

    $this->sArquivoConfiguracoes  = getenv('HOME') . '/.';
    $this->sArquivoConfiguracoes .= basename($this->getApplication()->getModel()->getProjeto()->name) . '_config.json';

    if ( $oInput->getOption('restart') ) {

      $this->criarArquivoConfiguracoes();

      $oOutput->writeln("<info>Configurações reiniciadas.</info>");
      return;
    }

    if ( !file_exists($this->sArquivoConfiguracoes) ) {
      $this->criarArquivoConfiguracoes();
    }

    /**
     * Editar usando editor 
     */
    if ( $oInput->getOption('edit') ) {

      $iStatus = $this->editarArquivoConfiguracoes();

      if ( $iStatus > 0 ) {
        throw new \Exception('Não foi possivel editar configurações');
      }

      return;
    }

    $oConfig = new \Config($this->sArquivoConfiguracoes);

    $sOutput       = PHP_EOL;
    $aIgnore       = $oConfig->get('ignore');
    $iTagRelease   = $oConfig->get('tag')->release;
    $tagsSprint    = $oConfig->get('tag')->sprint;
    $sBloquearPush = $oConfig->get('tag')->bloquearPush ? 'Sim' : 'Não';


    $sOutput .= "- <comment>Arquivo:</comment> " . PHP_EOL;
    $sOutput .= "  " .$this->sArquivoConfiguracoes . PHP_EOL;

    /**
     * Ignorar 
     */
    if ( !empty($aIgnore) ) {

      $sOutput .= PHP_EOL;
      $sOutput .= "- <comment>Ignorar:</comment>" . PHP_EOL;
      $sOutput .= '  ' . implode(PHP_EOL . '  ', $aIgnore) . PHP_EOL;
    }

    /**
     * Tags 
     */
    if ( !empty($iTagRelease) || !empty($tagsSprint) || !empty($sBloquearPush) ) {

      $sOutput .= PHP_EOL;
      $sOutput .= "- <comment>Tags:</comment>" . PHP_EOL;

      if ( !empty($iTagRelease) ) {

        $sOutput .= PHP_EOL;
        $sOutput .= "  <comment>Release:</comment>" . PHP_EOL;
        $sOutput .= '  ' . $iTagRelease . PHP_EOL;
      }

      if ( !empty($tagsSprint) ) {

        if ( is_array($tagsSprint) ) {

          $sOutput .= PHP_EOL;
          $sOutput .= "  <comment>Sprint:</comment>" . PHP_EOL;
          $sOutput .= '  ' . implode(', ', $tagsSprint). PHP_EOL;
        }

        if ( is_object($tagsSprint) ) {

          $sOutput .= PHP_EOL;
          $sOutput .= "  <comment>Sprint:</comment>" . PHP_EOL;

          foreach ( $tagsSprint as $sTag => $sDescricao ) {

            $sOutput .= '  ' . $sTag;

            if ( !empty($sDescricao) ) {
              $sOutput .= ': ' . $sDescricao;
            }

            $sOutput .=  PHP_EOL; 
          }
        }
      }

      if ( !empty($sBloquearPush) ) {

        $sOutput .= PHP_EOL;
        $sOutput .= "  <comment>Bloquear push:</comment>" . PHP_EOL;
        $sOutput .= '  ' . $sBloquearPush . PHP_EOL;
      }
    }

    $oOutput->writeln($sOutput);
  }

  private function editarArquivoConfiguracoes() {

    $pid = pcntl_fork();

    if ($pid == -1) {
      return 1;
    } 

    if ($pid == 0 ) { 

      array_unshift($this->aParametrosEditor, $this->sArquivoConfiguracoes);
      pcntl_exec($this->sCaminhoEditor, $this->aParametrosEditor);
    }

    pcntl_waitpid($pid, $status);
    return $status;
  }

  private function criarArquivoConfiguracoes() {

    $sConteudoArquivo  = '/**' . PHP_EOL;
    $sConteudoArquivo .= ' * ----------------------------------------------------------' . PHP_EOL;
    $sConteudoArquivo .= ' * Configuracoes                                             ' . PHP_EOL;
    $sConteudoArquivo .= ' * ----------------------------------------------------------' . PHP_EOL;
    $sConteudoArquivo .= ' */ ' . PHP_EOL;
    $sConteudoArquivo .= '{' . PHP_EOL;
    $sConteudoArquivo .= PHP_EOL;
    $sConteudoArquivo .= '  /** ' . PHP_EOL;
    $sConteudoArquivo .= '   * --------------------------------------------------------' . PHP_EOL;
    $sConteudoArquivo .= '   * Tags                                                    ' . PHP_EOL;
    $sConteudoArquivo .= '   * --------------------------------------------------------' . PHP_EOL;
    $sConteudoArquivo .= '   * configuracoes das tags' . PHP_EOL;
    $sConteudoArquivo .= '   */ ' . PHP_EOL;
    $sConteudoArquivo .= '  "tag" : { ' . PHP_EOL;
    $sConteudoArquivo .= '                                                             ' . PHP_EOL;
    $sConteudoArquivo .= '    /**' . PHP_EOL;
    $sConteudoArquivo .= '     * tag para usar em todos os commits ' . PHP_EOL;
    $sConteudoArquivo .= '     *' . PHP_EOL;
    $sConteudoArquivo .= '     * @var integer | string' . PHP_EOL;
    $sConteudoArquivo .= '     */' . PHP_EOL;
    $sConteudoArquivo .= '    "release" : null,' . PHP_EOL;
    $sConteudoArquivo .= PHP_EOL;
    $sConteudoArquivo .= '    /**                                                      ' . PHP_EOL;
    $sConteudoArquivo .= '     * Tags do sprint atual, usada no comentario do commit' . PHP_EOL;
    $sConteudoArquivo .= '     *' . PHP_EOL;
    $sConteudoArquivo .= '     * @var array | objetct' . PHP_EOL;
    $sConteudoArquivo .= '     */' . PHP_EOL;
    $sConteudoArquivo .= '    "sprint" : [' . PHP_EOL;
    $sConteudoArquivo .= '    ],' . PHP_EOL;
    $sConteudoArquivo .= '                                                             ' . PHP_EOL;
    $sConteudoArquivo .= '    /**' . PHP_EOL;
    $sConteudoArquivo .= '     * Bloquar commit' . PHP_EOL;
    $sConteudoArquivo .= '     * Ao usar o comando "cvsgit push" ' . PHP_EOL;
    $sConteudoArquivo .= '     * caso tag usada pelo comando "cvsgit add -t " for' . PHP_EOL;
    $sConteudoArquivo .= '     * diferente das tags do sprint, bloqueia push' . PHP_EOL;
    $sConteudoArquivo .= '     *' . PHP_EOL;
    $sConteudoArquivo .= '     * @var boolean' . PHP_EOL;
    $sConteudoArquivo .= '     */ ' . PHP_EOL;
    $sConteudoArquivo .= '    "bloquearPush" : false' . PHP_EOL;
    $sConteudoArquivo .= '  },' . PHP_EOL;
    $sConteudoArquivo .= PHP_EOL;
    $sConteudoArquivo .= '  /**' . PHP_EOL;
    $sConteudoArquivo .= '   * --------------------------------------------------------' . PHP_EOL;
    $sConteudoArquivo .= '   * Ignorar                                                 ' . PHP_EOL;
    $sConteudoArquivo .= '   * --------------------------------------------------------' . PHP_EOL;
    $sConteudoArquivo .= '   * Arquivos para ignorar modificações' . PHP_EOL;
    $sConteudoArquivo .= '   *' . PHP_EOL;
    $sConteudoArquivo .= '   * @var array' . PHP_EOL;
    $sConteudoArquivo .= '   */' . PHP_EOL;
    $sConteudoArquivo .= '  "ignore" : [' . PHP_EOL;
    $sConteudoArquivo .= '  ]' . PHP_EOL;
    $sConteudoArquivo .= PHP_EOL;
    $sConteudoArquivo .= '}' . PHP_EOL;

    $lCriarConfiguracoes = file_put_contents($this->sArquivoConfiguracoes, $sConteudoArquivo);

    if ( !$lCriarConfiguracoes ) {
      throw new \Exception("Não foi possivel criar arquivo de configurações: $this->sArquivoConfiguracoes");
    }

    return $lCriarConfiguracoes;
  }

}
