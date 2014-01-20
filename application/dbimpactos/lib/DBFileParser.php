<?php
require_once __DIR__ . '/FileParser.php';

class DBFileParser extends FileParser {
  
  private $aFuncoesUtilizadas = array();
  private $aConstantesUtilizadas = array();

  public function __construct($pathFile, $pathProject = '/var/www/dbportal_prj/') {

    try {

      parent::__construct($pathFile, $pathProject);

    } catch (Exception $oException) {
      
      $oOutput = new \Symfony\Component\Console\Output\ConsoleOutput();
      $oOutput->writeln('<error>' . $oErro->getMessage() . '</error>');
    }

    $this->processar();
  }

  public function processar() {

    $tokenizer = $this->tokenizer;
    $this->rewind();

    while($tokenizer->valid()) {

      $token = $tokenizer->current();

      if ($token->getValue() === T_STRING) {

        if ( $token->getCode() === 'db_utils' ) {

          $this->processarGetDao();
          continue;
        }

        /**
         * Token é string
         * - Caso não encontre funcao procura constant
         */
        if (!$this->processarFuncao()) {
          $this->processarConstante();
        }
      }

      $tokenizer->next();
    }

  } 

  public function processarGetDao() {
    
    $tokenValido = $this->findTokenForward(T_STRING);

    if (!$tokenValido || $tokenValido->getCode() !== 'getDao') {
      return false;
    }

    $token = $this->findTokenForward(array(T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE));
    
    if (!$token) {
      return false;
    }

    $sClasse = 'cl_' . $this->clearEncapsedString($token->getCode());

    $this->declaring[] = array('line' => $token->getLine(), 'class' => $sClasse);
    return true;
  }

  public function processarFuncao() {

    $funcao = $this->tokenizer->current()->getCode();
    $linha  = $this->tokenizer->current()->getLine();

    $oTokenValidaFuncao = $this->findTokenForward('(');

    if (!$oTokenValidaFuncao) {
      return false;
    }

    $this->aFuncoesUtilizadas[] = $funcao;
    return true;
  }

  public function processarConstante() {

    $sConstante = $this->tokenizer->current()->getCode();
    $linha = $this->tokenizer->current()->getLine();   

    if (in_array(strtoupper($sConstante), array(FALSE, TRUE, NULL)) ) { 
      return false;
    }

    $this->aConstantesUtilizadas[] = $sConstante;
    return true;
  }

  public function getFuncoesUtilizadas() {
    return $this->aFuncoesUtilizadas;
  }

  public function getConstantesUtilizadas() {
    return $this->aConstantesUtilizadas;
  }

}


//$Tokenizer = new DBTokenizer('/var/www/dbportal_prj/model/dataManager.php');
//$Tokenizer = new DBTokenizer('/var/www/dbportal_prj/pes2_emissao_pdf002.php');
//$Tokenizer = new DBTokenizer('/var/www/dbportal_prj/scripts/classes/DBViewEncerramentoAvaliacoesFiltro.classe.js');

//print_r($Tokenizer->getDebugTokens());
//print_r($Tokenizer->getConstants());
//print_r($Tokenizer->getClasses());
// print_r($Tokenizer->getFunctions());
// print_r($Tokenizer->getRequires());
// print_r($Tokenizer->getDeclaring());
// print_r($Tokenizer->getLog());
