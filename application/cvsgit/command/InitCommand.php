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

    if ( !file_exists('CVS/Repository') ) {
      throw new \Exception('Diretório atual não é um repositorio CVS.');
    }

    $sDiretorioAtual = getcwd();
    $sRepositorio = trim(file_get_contents('CVS/Repository'));

    $oFileDataBase = $this->getApplication()->getFileDataBase();
    $aProjetos = $oFileDataBase->selectAll("select name, path from project where name = '$sRepositorio' or path = '$sDiretorioAtual'");

    /**
     * Diretorio atual ja inicializado 
     */
    foreach( $aProjetos as $oProjeto ) {

      /**
       * Repositorio 
       */
      if ( $oProjeto->name == $sRepositorio ) {

        $oOutput->writeln(sprintf('<info>"%s" já inicializado</info>', $sRepositorio));
        return true;
      }

      /**
       * Diretorio atual 
       */
      if ( $oProjeto->path == $sDiretorioAtual ) {

        $oOutput->writeln(sprintf('<info>"%s" já inicializado</info>', $sDiretorioAtual));
        return true;
      }
    }   

    $oFileDataBase->insert('project', array('name' => $sRepositorio, 'path' => $sDiretorioAtual, 'date' => date('Y-m-d H:i:s')));

    $oOutput->writeln(sprintf('<info>"%s" inicializado</info>', $sRepositorio));
  }

}
