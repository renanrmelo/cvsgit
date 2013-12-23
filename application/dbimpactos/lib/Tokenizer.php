<?php 

class Tokenizer {

  protected $classes = array();
  protected $functions = array();
  protected $requires = array();
  protected $declaring = array();

  protected $pathRequire;
  protected $pathFile;
  protected $log = "";

  /**
   * lines
   */
  protected $totalLines = 0;
  protected $current = 0;

  protected $tokens = array();
  protected $currentClassName;
  protected $brackets;
  protected $typeRequire = array(T_REQUIRE, T_REQUIRE_ONCE, T_INCLUDE, T_INCLUDE_ONCE);

  const FILE_PATTERN = '/\b(?P<files>[\/\w-.]+\.php)\b/mi';

  public function __construct($pathFile, $pathProject = '/var/www/dbportal_prj/') {

    if ( !file_exists($pathFile) ) {
      throw new Exception('File not exists: ' . $pathFile);
    }

    // $source = file_get_contents($pathFile);

    $this->pathFile = $pathFile;
    $this->pathRequire = dirname($pathFile) . '/';
    $this->pathProject = $pathProject;

    $source = '';
    $file = fopen($pathFile, "r"); 
    while ( !feof($file) ) { 

      $source .= fgets($file, 4096); 
      $this->totalLines++;
    } 
    fclose($file); 

    if ( empty($source) ) {
      return;
    }

    $this->tokens = @token_get_all($source);    
    $source = null;

    $this->parse();
  }

  public function parse() {

    $count = count($this->tokens);
    $this->brackets  = 0;

    for ($this->current = 0; $this->current < $count; $this->current++) {

      $token =  $this->tokens[$this->current];

      if ( is_scalar($token) ) {

        if ( trim($token) === '{' ) {
          $this->brackets++;
        }

        if ( trim($token) === '}' ) {
          $this->brackets--;
        }

        continue;
      }

      $type  = $this->tokens[$this->current][0];
      $value = $this->tokens[$this->current][1];

      if ( trim($value) === '{' )  {
        $this->brackets++;
      } 
      
      if ( trim($value) === '}' )  {
        $this->brackets--;
      } 

      if ( $type === T_INLINE_HTML ) {

        $this->parseHTML($this->current);
        continue;
      }

      /**
       * Require 
       */
      if ( in_array($type, $this->typeRequire) ) {

        $this->parseRequire();
        continue;
      }

      if ( $type === T_NEW ) {

        $this->parseDeclaring();
        continue;
      }

      if ( $type === T_FUNCTION ) {

        $this->parseFunction();
        continue;
      }

      if ( $type === T_CLASS ) {

        $this->parseClass();
        continue;
      }

      if ( $type === T_CONSTANT_ENCAPSED_STRING || $type === T_ENCAPSED_AND_WHITESPACE ) {

        $this->parseString();
        continue;
      }

      if ( $type === T_DOUBLE_COLON) {
        
        $this->parseStatic();
        continue;
      }

    }

  }

  public function getFileName($string) {

    if ( strpos($string, '.php') === false ) {
      return false;
    }

    preg_match_all(Tokenizer::FILE_PATTERN, $string, $matches);
    return $matches['files'];
  }

  public function parseNext($type) {    

    $next = $this->current + 1;

    while ( !empty($this->tokens[$next] ) ) {

      if ( is_scalar($this->tokens[$next]) ) {

        if ( $this->tokens[$next] === $type ) {
          return $type;
        }

        $next++;                
        continue;
      }

      if ( $this->tokens[$next][0] === $type ) {

        $tokens = array( $this->tokens[$next][2], $this->tokens[$next][1] );
        $this->current = $next;
        return $tokens;
      }

      if ( $this->tokens[$next][0] === ';' ) {
        return false;
      }

      $next++;
    }

    return false; 
  }

  public function parsePrev($type) {    
    
    $prev =  $this->current - 1;   

    while ( !empty($this->tokens[$prev] ) ) {
      
      if ( is_scalar($this->tokens[$prev]) ) {

        if ( $this->tokens[$prev] === $type ) {

          $this->current = $prev;
          return $type;
        }

        $prev--;                
        continue;
      }

      if ( $this->tokens[$prev][0] === $type ) {

        $tokens = array( $this->tokens[$prev][2], $this->tokens[$prev][1] );
        $this->current = $prev;
        return $tokens;
      }

      if ( $this->tokens[$prev][0] === ';' ) {
        return false;
      }

      $prev--;
    }

    return false; 
  }

  public function parseClass() {

    $dataClass = $this->parseNext(T_STRING);

    if ( empty($dataClass) ) {
      return false;
    }

    $line  = $dataClass[0];
    $class = $dataClass[1];

    $this->currentClassName = $class;
    $this->classes[$this->currentClassName] = array('line' => $line, 'method' => array());
  }

  public function parseFunction() {

    $dataFunction = $this->parseNext(T_STRING);

    if ( empty($dataFunction) ) {
      return false;
    }

    $line     = $dataFunction[0];
    $function = $dataFunction[1];

    if ( $this->brackets === 0 ) {
      $this->currentClassName = null;
    }

    if ( empty($this->currentClassName) ) {

      $this->functions[] = array('line' => $line, 'function' => $function);
      return;
    }

    $this->classes[$this->currentClassName]['method'][] = array('line' => $line, 'function' => $function);
  }

  public function parseString() {

    $string = $this->tokens[$this->current][1];
    $line   = $this->tokens[$this->current][2];

    $files = $this->getFileName($string);

    if ( empty($files) ) {
      return false;
    }

    foreach ( $files as $require ) {

      $requireFile = realpath($this->pathProject . $require);

      if ( empty($requireFile) ) {

        $this->log .= "\n ----------------------------------------------------------------------------------------------------\n";
        $this->log .= " - Arquivo de require não encontrado: " . $this->pathFile . " :" . $line;
        $this->log .= "\n   Tokenizer::parseString(): ";
        $this->log .= "\n   " . $this->pathProject . $require;
        $this->log .= "\n ----------------------------------------------------------------------------------------------------\n";
        continue;
      }

      $this->requires[] = array('line' => $line, 'file' => $requireFile);
    }
  }

  public function parseRequire() {

    $dataRequire = $this->parseNext(T_CONSTANT_ENCAPSED_STRING);

    if ( empty($dataRequire) ) {
      return false;
    }

    $require = str_replace(array('"', "'"), '', $dataRequire[1]);

    $requireFile = $this->pathRequire . $require; 

    if ( !file_exists($requireFile) ) {
      $requireFile = $this->pathProject . $require; 
    } 

    $requireFile = realpath($requireFile);

    if ( empty($requireFile) ) {

      $this->log .= "\n ----------------------------------------------------------------------------------------------------\n";
      $this->log .= " - Arquivo de require não encontrado: " . $this->pathFile . " :" . $dataRequire[0];
      $this->log .= "\n   Tokenizer::parseRequire(): ";
      $this->log .= "\n   " . $this->pathRequire . $require ."  || " . $this->pathProject . $require;
      $this->log .= "\n ----------------------------------------------------------------------------------------------------\n";
      return false;
    }

    $this->requires[] = array('line' => $dataRequire[0], 'file' => $requireFile); 
    return true;
  }

  public function parseDeclaring() {

    $dataDeclaring = $this->parseNext(T_STRING);

    if ( empty($dataDeclaring) ) {
      return false;
    }

    if ( in_array($dataDeclaring[1], array('stdClass', 'Exception')) ) {
      return false;
    } 

    $this->declaring[] = array('line' => $dataDeclaring[0], 'class' => $dataDeclaring[1]);
    return true;
  }

  public function parseHTML() {

    $currentLine = $this->tokens[$this->current][2];
    $lines = explode("\n", $this->tokens[$this->current][1]);

    foreach ($lines as $contentLine) {

      $files = $this->getFileName($contentLine);

      if ( empty($files) ) {

        $currentLine++;
        continue;
      }

      foreach ( $files as $require ) {

        $requireFile = realpath($this->pathProject . $require);

        if ( empty($requireFile) ) {

          $this->log .= "\n ----------------------------------------------------------------------------------------------------\n";
          $this->log .= " - Arquivo de require não encontrado: " . $this->pathFile . " :" . $currentLine;
          $this->log .= "\n   Tokenizer::parseHTML(): ";
          $this->log .= "\n   " . $this->pathProject . $require;
          $this->log .= "\n ----------------------------------------------------------------------------------------------------\n";
          continue;
        }

        $this->requires[] = array('line' => $currentLine, 'file' => $requireFile);
      }

      $currentLine++;
    } 
  }

  public function parseStatic() {

    $current = $this->current;
    $staticClasses  = $this->parsePrev(T_STRING);
    //$metodo = $this->parseNext(T_STRING);    
    
    $this->current = $current;
    if ( empty($staticClasses)) {
      return false;
    }

    $sNomeArquivo = $staticClasses[1];
    $iLinha = $staticClasses[0];

    $this->declaring[] = array('line' => $iLinha, 'class' => $sNomeArquivo);

    return true;
  }

  public function getClasses() {
    return $this->classes;
  }

  public function getFunctions() {
    return $this->functions;
  }

  public function getRequires() {
    return $this->requires;
  }

  public function getDeclaring() {
    return $this->declaring;
  }

  public function getLog() {
    return $this->log;
  }

  public function getTotalLines() {
    return $this->totalLines;
  }

  public function __destruct() {
    $this->clearMemory();
  }

}


// @todo - nao achou o arquivo pro3_consultaprocesso002.php 
// pro3_imprimirconsultaprocesso.php
// $Tokenizer = new Tokenizer('/var/www/dbportal_prj/pro3_consultaprocesso002.php');

//echo token_name(314);

//$Tokenizer = new Tokenizer('/var/www/dbportal_prj/pro3_consultaprocesso002.php');

// $Tokenizer = new Tokenizer('/var/www/dbportal_prj/edu4_encerramentoavaliacao.RPC.php');
// $Tokenizer = new Tokenizer('/var/www/dbportal_prj/model/educacao/progressaoparcial/ProgressaoParcialAluno.model.php');


// print_r($Tokenizer->getClasses());
// print_r($Tokenizer->getFunctions());
//print_r($Tokenizer->getRequires());
// print_r($Tokenizer->getDeclaring());
