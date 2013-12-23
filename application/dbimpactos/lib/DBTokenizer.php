<?php
require_once __DIR__ . '/Tokenizer.php';

class DBTokenizer extends Tokenizer {
  
  public function __construct($pathFile, $pathProject = '/var/www/dbportal_prj/') {

    parent::__construct($pathFile, $pathProject);
    $this->processar();
  }

  public function processar() {

    $count = count($this->tokens);
    $this->brackets  = 0;    

    //print_r($this->tokens);

    for ($this->current = 0; $this->current < $count; $this->current++) {

      $token =  $this->tokens[$this->current];

       if ( is_scalar($token) ) {
        continue;
      }

      $type  = $this->tokens[$this->current][0];
      $value = $this->tokens[$this->current][1];

      if ( $value == 'db_utils' ) {
        $this->processarGetDao();
      }

    }

  } 

  public function processarGetDao() {
    
    $aDadosGetDao = $this->parseNext(T_STRING);

    if (empty($aDadosGetDao[1]) || $aDadosGetDao[1] !== 'getDao') {
      return false;
    }

    $aDadosDao = $this->parseNext(T_CONSTANT_ENCAPSED_STRING);
    
    if ( empty($aDadosDao)) {
      return false;
    }

    $sNomeArquivo = 'cl_' . str_replace(array('"', "'"), '', $aDadosDao[1]);
    $iLinha = $aDadosDao[0];

    $this->declaring[] = array('line' =>$iLinha, 'class' => $sNomeArquivo);
    return true;
  }

}


//$Tokenizer = new DBTokenizer('/var/www/dbportal_prj/teste.php');
//$Tokenizer = new DBTokenizer('/var/www/dbportal_prj/scripts/classes/DBViewEncerramentoAvaliacoesFiltro.classe.js');

//echo "\n ::: " . token_name(376);
//echo "\n getInstance: " . token_name(307);
//echo "\n assinatura: " . token_name(309);
//echo "\n assinatura: " . token_name(305);
//echo "\n\n";


print_r($Tokenizer->getClasses());
print_r($Tokenizer->getFunctions());
print_r($Tokenizer->getRequires());
print_r($Tokenizer->getDeclaring());
print_r($Tokenizer->getLog());