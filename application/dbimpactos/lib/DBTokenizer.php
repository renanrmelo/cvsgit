<?php
require_once __DIR__ . '/Tokenizer.php';

class DBTokenizer extends Tokenizer {
  
  private $aFuncoesUtilizadas = array();
  private $aConstantesUtilizadas = array();

  public function __construct($pathFile, $pathProject = '/var/www/dbportal_prj/') {

    parent::__construct($pathFile, $pathProject);
    $this->processar();
  }

  public function processar() {

    $count = count($this->tokens);
    $this->brackets  = 0;    

    for ($this->current = 0; $this->current < $count; $this->current++) {

      $token =  $this->tokens[$this->current];

       if ( is_scalar($token) ) {
        continue;
      }

      $type  = $this->tokens[$this->current][0];
      $value = $this->tokens[$this->current][1];

      if ($type === T_STRING) {
        if (  $value == 'db_utils' ) {
          $this->processarGetDao();
          continue;
        }

        /**
         * Token é string
         * - Caso não encontre funcao procura constant
         */
        if ( !$this->processarFuncao() ) {
          $this->processarConstante();
        }
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

  public function processarFuncao() {

    $funcao = $this->tokens[$this->current][1];
    $linha   = $this->tokens[$this->current][2];

    $aDadosFuncao = $this->parseNext('(');

    if ( empty($aDadosFuncao)) {
      return false;
    }

    $this->aFuncoesUtilizadas[] = $funcao;
    return true;
  }

  public function processarConstante() {

    $constante = $this->tokens[$this->current][1];
    $linha   = $this->tokens[$this->current][2];   

    $this->aConstantesUtilizadas[] = $constante;
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
