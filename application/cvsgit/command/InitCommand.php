<?php
namespace CVS;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class InitCommand extends Command {

  public function configure() {

    $this->setName('init');
    $this->setDescription('Inicializa diretório atual');
    $this->setHelp('Inicializa diretório atual');
  }

  public function execute($oInput, $oOutput) {

    $oApplication = $this->getApplication();

    /**
     * Cria diretorio 
     */
    if ( !is_dir($oApplication->sDiretorioObjetos) ) {
      mkdir($oApplication->sDiretorioObjetos, 0777, true);
    }

    /**
     * Cria arquivo com objeto vazio 
     */
    if ( !file_exists($oApplication->sDiretorioObjetos . 'Objects') ) {
      file_put_contents($oApplication->sDiretorioObjetos . 'Objects', serialize(array()));
    }

    $aProjetos       = unserialize(file_get_contents($oApplication->sDiretorioObjetos . 'Objects'));
    $sDiretorioAtual = getcwd();

    /**
     * Diretorio atual ja inicializado 
     */
    if ( in_array($sDiretorioAtual, $aProjetos) ) {

      $oOutput->writeln(sprintf('<info>"%s" já inicializado</info>', getcwd()));
      return true;
    }   

    $aProjetos[] = $sDiretorioAtual;

    file_put_contents($oApplication->sDiretorioObjetos . 'Objects', serialize($aProjetos));

    if ( file_exists($oApplication->sDiretorioObjetos) ) {
      $oOutput->writeln(sprintf('<info>"%s" inicializado</info>', getcwd()));
    }
  }

}
