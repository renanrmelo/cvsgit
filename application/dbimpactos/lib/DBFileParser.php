<?php
require_once __DIR__ . '/FileParser.php';

class DBFileParser extends FileParser {
  
  private $aFuncoesUtilizadas = array();
  private $aConstantesUtilizadas = array();

  public function __construct($pathFile, $pathProject = '/var/www/dbportal_prj/') {

    parent::__construct($pathFile, $pathProject);
    $this->processar();
  }

  public function processar() {

    $tokenizer = $this->tokenizer;
    $this->rewind();

    while($tokenizer->valid()) {

      $oToken = $tokenizer->current();

      if ($oToken->getValue() === T_STRING) {

        if ( $oToken->getCode() === 'db_utils' ) {

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

    $oToken = $this->findTokenForward(array(T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE));
    
    if (!$oToken) {
      return false;
    }

    $sClasse = 'cl_' . $this->clearEncapsedString($oToken->getCode());

    $this->declaring[] = array('line' => $oToken->getLine(), 'class' => $sClasse);
    return true;
  }

  public function processarFuncao() {

    /**
     * Debugar 
     */
    if ( !$this->tokenizer->valid() ) {
      return false;
    }

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

    if (!$this->tokenizer->valid()) {
      return false;
    }

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
