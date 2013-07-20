<?php
namespace CVS;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class PullCommand extends Command {

  public function configure() {

    $this->setName('pull');
    $this->setDescription('Baixa atualizações do repositorio');
    $this->setHelp('Baixa atualizações do repositorio');
  }

  public function execute($oInput, $oOutput) {

    $oOutput->write("baixando atualizações...\r");

    exec('cvs update -dR 2> /tmp/cvsgit_last_error', $aRetornoComandoUpdate, $iStatusComandoUpdate);

    if ( $iStatusComandoUpdate > 0 ) {

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
      throw new \Exception("Erro ao atualizar permissões dos arquivos, verifique arquivo de log");
    }

  }

}