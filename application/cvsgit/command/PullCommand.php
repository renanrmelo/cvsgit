<?php
namespace CVS;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Exception;

class PullCommand extends Command {

  /**
   * Configura o comando
   *
   * @access public
   * @return void
   */
  public function configure() {

    $this->setName('pull');
    $this->setDescription('Baixa atualizações do repositorio');
    $this->setHelp('Baixa atualizações do repositorio');
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

    $oOutput->write("baixando atualizações...\r");

    exec('cvs update -dR 2> /tmp/cvsgit_last_error', $aRetornoComandoUpdate, $iStatusComandoUpdate);

    /**
     * Caso CVS encontre conflito, retorna erro 1
     */
    if ( $iStatusComandoUpdate > 1 ) {

      $oOutput->writeln('<error>Erro nº ' . $iStatusComandoUpdate. ' ao execurar cvs update -dR:' . "\n" . $this->getApplication()->getLastError() . '</error>');
      return $iStatusComandoUpdate;
    }

    $oOutput->writeln(str_repeat(' ', \Shell::columns()) . "\r" . "Atualizações baixados");

    $sComandoRoot = '';

    /**
     * Senha do root
     */
    $sSenhaRoot = $this->getApplication()->getConfig('senhaRoot');

    /**
     * Executa comando como root 
     * - caso for existir senha no arquivo de configuracoes
     */
    if ( !empty($sSenhaRoot) ) {
      $sComandoRoot = "echo '{$sSenhaRoot}' | sudo -S ";
    }

    exec($sComandoRoot . 'chmod 777 -R ' . getcwd() . ' 2> /tmp/cvsgit_last_error', $aRetornoComandoPermissoes, $iStatusComandoPermissoes);

    if ( $iStatusComandoPermissoes > 0 ) {
      throw new Exception("Erro ao atualizar permissões dos arquivos, verifique arquivo de log");
    }

  }

}
