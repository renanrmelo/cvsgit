<?php
namespace CVS;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class ConfigCommand extends Command {

  public function configure() {

    $this->setName('config');
    $this->addArgument('configuracao', InputArgument::OPTIONAL, 'Arquivo, nome ou string json');
    $this->setDescription('Exibe configurações da aplicação');
    $this->setHelp(
      'Exibe ou define configurações da aplicação' . PHP_EOL .
      'Caso não for informado nenhum parametro, exibe as configurações' . PHP_EOL .
      'Parametro "configuracao" poder ser: ' . PHP_EOL . 
      '- Arquivo: Arquivo contendo configurações em formato json, exemplo: cvsgit config configuracoes.json' . PHP_EOL .
      '- String : String no formato json, exemplo cvsgit config \'"ignore" : ["libs/db_conn.php","debug.php"]\'' . PHP_EOL .
      '- Nome   : Nome da configuracao, aplicação ira solicitar o valor, exeplo: cvsgit config tag' 
    );
  }

  public function execute($oInput, $oOutput) {

    $oConfig = $this->getApplication()->getConfig();

    // @todo receber por parametro arquivo de configuracao, ou string json

    //print_r($oConfig);
    print_r($oInput->getArgument('configuracao'));
    print_r(json_decode("{" . $oInput->getArgument('configuracao'). "}"));
    //print_r(json_decode($oInput->getArgument('configuracao')));
  }

}
