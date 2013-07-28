<?php
namespace CVS;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class TagCommand extends Command {

  public function configure() {

    $this->setName('tag');
    $this->setDescription('Lista, cria e remove tags');
    $this->addArgument('arquivos', InputArgument::IS_ARRAY, 'Arquivos para alterar tags');
    $this->setHelp('Lista, cria e remove tags');
  }

  /**
   * - adicionar/remover tag 1 ou varios arquivos
   * - caso esteje nos arquivos ja adicinad
   */
  public function execute($oInput, $oOutput) {

  }

}
