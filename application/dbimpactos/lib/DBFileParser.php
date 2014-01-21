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
      }

      $tokenizer->next();
    }

  } 

  public function processarGetDao() {
    
    $oProximoToken = $this->findTokenForward(T_STRING);

    if (!$oProximoToken || $oProximoToken->getCode() !== 'getDao') {
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
