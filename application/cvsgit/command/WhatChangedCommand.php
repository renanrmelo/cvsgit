<?php
namespace CVS;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class WhatChangedCommand extends Command {

  private $sParametroData;

  public function configure() {

    $this->setName('whatchanged');
    $this->setDescription('what changed');
    $this->setHelp('what changed');
  }

  public function execute($oInput, $oOutput) {

    $aArquivosCommitados = $this->getApplication()->getModel()->getArquivosCommitados();

    if ( empty($aArquivosCommitados) ) {
      throw new \Exception("Nenhum arquivo commitado ainda.");
    }

    $oOutput->writeln("");

    foreach ( $aArquivosCommitados as $oDadosCommit ) {

      $sTitulo = "- <comment>" . date('d/m/Y', strtotime($oDadosCommit->date)) . "</comment> as " . date('H:s:i', strtotime($oDadosCommit->date));
      $oOutput->writeln($sTitulo . ": " . $oDadosCommit->title);

      $oTabela = new \Table();
      $oTabela->setHeaders(array('1','1','1','1'));

      foreach ( $oDadosCommit->aArquivos as $oArquivo ) {

        $sArquivo = $this->getApplication()->clearPath($oArquivo->name);
        $oTabela->addRow(array($oArquivo->type, " $sArquivo", " T$oArquivo->tag", " $oArquivo->message"));
      }

      $oOutput->writeln("  " . str_replace("\n" , "\n  ", $oTabela->render(true)));
    }

  }

}
