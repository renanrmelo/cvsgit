<?php
namespace CVS;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class ConfigCommand extends Command {

  private $sArquivoConfiguracoesTemporarios = '/tmp/cvsgit.json'; 

  public function configure() {

    $this->setName('config');
    $this->setDescription('Exibe configurações da aplicação');
    $this->setHelp('Exibe configurações da aplicação');
    $this->addOption('edit',  'e', InputOption::VALUE_NONE, 'Editar configurações');
    $this->addOption('reset', 'r', InputOption::VALUE_NONE, 'Resetar configurações');
  }

  public function execute($oInput, $oOutput) {

    if ( $oInput->getOption('reset') ) {
      return $this->criarArquivoConfiguracoes();
    }

    /**
     * Editar usando editor 
     */
    if ( $oInput->getOption('edit') ) {

      $this->carregarArquivoConfiguracoes();

      $iStatus = $this->editarArquivoConfiguracoes();

      if ( $iStatus > 0 ) {
        throw new \Exception('Não foi possivel salvar configurações, ' . $iStatus);
      }

      $lSalvar = $this->salvarArquivoConfiguracoes();

      if ( !$lSalvar ) {
        throw new \Exception('Não foi possivel salvar configurações');
      }

      $oOutput->writeln('<info>Configurações salvas</info>');
      return;
    }

    $sArquivo = $this->getApplication()->getDiretorioObjetos() . md5('config_' . $this->getApplication()->getProjeto());
    $oConfig = new \Config($sArquivo);

    $sOutput       = '';
    $aIgnore       = $oConfig->get('ignore');
    $iTagRelease   = $oConfig->get('tag')->release;
    $tagsSprint    = $oConfig->get('tag')->sprint;
    $sBloquearPush = $oConfig->get('tag')->bloquearPush ? 'Sim' : 'Não';

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

        $sOutput .= PHP_EOL;
        $sOutput .= "  <comment>Sprint:</comment>" . PHP_EOL;

        if ( is_array($tagsSprint) ) {
          $sOutput .= '  ' . implode(', ', $tagsSprint). PHP_EOL;
        }

        if ( is_object($tagsSprint) ) {

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
      pcntl_exec('/usr/bin/vim', array($this->sArquivoConfiguracoesTemporarios, '-c', 'set ft=javascript'));
    }

    pcntl_waitpid($pid, $status);
    return $status;
  }

  private function salvarArquivoConfiguracoes() {

    try {
      $oConfig  = new \Config($this->sArquivoConfiguracoesTemporarios);
    } catch(\Exception $oErro) {
      throw new \Exception('Configuracões não salvas, json inválido.');
    }

    $sArquivo = $this->getApplication()->getDiretorioObjetos() . md5('config_' . $this->getApplication()->getProjeto());
    return file_put_contents($sArquivo, file_get_contents($this->sArquivoConfiguracoesTemporarios));
  }

  private function carregarArquivoConfiguracoes() {

    $sArquivo = $this->getApplication()->getDiretorioObjetos() . md5('config_' . $this->getApplication()->getProjeto());

    if ( !file_exists($sArquivo) ) {
      $this->criarArquivoConfiguracoes();
    }

    return file_put_contents($this->sArquivoConfiguracoesTemporarios, file_get_contents($sArquivo));
  }

  private function criarArquivoConfiguracoes() {

    $sConteudoArquivo  = '/**' . PHP_EOL;
    $sConteudoArquivo .= ' * ----------------------------------------------------------' . PHP_EOL;
    $sConteudoArquivo .= ' * Configuracoes                                             ' . PHP_EOL;
    $sConteudoArquivo .= ' * ----------------------------------------------------------' . PHP_EOL;
    $sConteudoArquivo .= ' */ ' . PHP_EOL;
    $sConteudoArquivo .= '{' . PHP_EOL;
    $sConteudoArquivo .= '                                                             ' . PHP_EOL;
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
    $sConteudoArquivo .= '     */' . PHP_EOL;
    $sConteudoArquivo .= '    "release" : null,' . PHP_EOL;
    $sConteudoArquivo .= '                                                             ' . PHP_EOL;
    $sConteudoArquivo .= '    /**                                                      ' . PHP_EOL;
    $sConteudoArquivo .= '     * Tags do sprint atual, usada no comentario do commit' . PHP_EOL;
    $sConteudoArquivo .= '     */' . PHP_EOL;
    $sConteudoArquivo .= '    "sprint" : {' . PHP_EOL;
    $sConteudoArquivo .= '    },' . PHP_EOL;
    $sConteudoArquivo .= '                                                             ' . PHP_EOL;
    $sConteudoArquivo .= '    /**' . PHP_EOL;
    $sConteudoArquivo .= '     * Bloquar commit, ao usar o comando "cvsgit push" ' . PHP_EOL;
    $sConteudoArquivo .= '     * caso tag usada pelo comando "cvsgit add -t " for' . PHP_EOL;
    $sConteudoArquivo .= '     * diferente das tags do sprint' . PHP_EOL;
    $sConteudoArquivo .= '     */ ' . PHP_EOL;
    $sConteudoArquivo .= '    "bloquearPush" : false' . PHP_EOL;
    $sConteudoArquivo .= '  },' . PHP_EOL;
    $sConteudoArquivo .= '                                                             ' . PHP_EOL;
    $sConteudoArquivo .= '  /**' . PHP_EOL;
    $sConteudoArquivo .= '   * --------------------------------------------------------' . PHP_EOL;
    $sConteudoArquivo .= '   * Ignorar                                                 ' . PHP_EOL;
    $sConteudoArquivo .= '   * --------------------------------------------------------' . PHP_EOL;
    $sConteudoArquivo .= '   * Arquivos para ignorar' . PHP_EOL;
    $sConteudoArquivo .= '   */' . PHP_EOL;
    $sConteudoArquivo .= '  "ignore" : [' . PHP_EOL;
    $sConteudoArquivo .= '    "libs/db_conn.php"' . PHP_EOL;
    $sConteudoArquivo .= '  ]' . PHP_EOL;
    $sConteudoArquivo .= '' . PHP_EOL;
    $sConteudoArquivo .= '}' . PHP_EOL;

    $sArquivo = $this->getApplication()->getDiretorioObjetos() . md5('config_' . $this->getApplication()->getProjeto());

    return file_put_contents($sArquivo, $sConteudoArquivo);
  }

}
