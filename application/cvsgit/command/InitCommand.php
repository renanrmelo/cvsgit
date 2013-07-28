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
    $this->addOption('force', 'f', InputOption::VALUE_NONE, 'Forçar inicialização do diretório');
  }

  public function execute($oInput, $oOutput) {

    if ( !file_exists('CVS/Repository') ) {
      throw new \Exception('Diretório atual não é um repositorio CVS.');
    }

    $sDiretorioAtual = getcwd();
    $sRepositorio = trim(file_get_contents('CVS/Repository'));

    if ( $oInput->getOption('force') ) {

      if ( file_exists(CONFIG_DIR . 'cvsgit.db') ) {
        if ( !unlink(CONFIG_DIR . 'cvsgit.db') ) {
          throw new \Exception("Não foi possivel remover banco de dados: " . CONFIG_DIR . 'cvsgit.db');
        }
      }

      if ( file_exists(CONFIG_DIR . $sRepositorio . '_config.json') ) {
        if ( !unlink(CONFIG_DIR . $sRepositorio . '_config.json') ) {
          throw new \Exception("Não foi possivel remover configurações: " . CONFIG_DIR . $sRepositorio . '_config.json');
        }
      }
    }

    if ( file_exists(CONFIG_DIR . 'cvsgit.db') ) {

      $oDataBase = $this->getApplication()->getModel()->getDataBase();
      $aProjetos = $oDataBase->selectAll("select name, path from project where name = '$sRepositorio' or path = '$sDiretorioAtual'");

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

    }

    if ( !is_dir(CONFIG_DIR) && !mkdir(CONFIG_DIR) ) {
      throw new \Exception('Não foi possivel criar diretório: ' . CONFIG_DIR);
    }

    $lArquivoConfiguracoes = copy(APPLICATION_DIR . 'cvsgit/install/config.json', CONFIG_DIR . $sRepositorio . '_config.json');

    if ( !$lArquivoConfiguracoes ) {
      throw new \Exception("Não foi possivel criar arquivo de configurações no diretório: " . CONFIG_DIR );
    }

    $lArquivoBancoDados = copy(APPLICATION_DIR . 'cvsgit/install/cvsgit.db', CONFIG_DIR . 'cvsgit.db');

    if ( !$lArquivoBancoDados ) {
      throw new \Exception("Não foi possivel criar arquivo do banco de dados no diretório: " . CONFIG_DIR );
    }

    $oDataBase = new \FileDataBase(CONFIG_DIR . 'cvsgit.db');
    $oDataBase->begin();

    $oDataBase->insert('project', array(
      'name' => $sRepositorio,
      'path' => $sDiretorioAtual, 
      'date' => date('Y-m-d H:i:s')
    ));

    $oOutput->writeln(sprintf('<info>"%s" inicializado</info>', $sRepositorio));
    $oDataBase->commit();
  }

}
