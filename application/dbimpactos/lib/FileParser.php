<?php
require_once __DIR__ . '/Tokenizer.php';

/**
 * File Parser
 *
 * @todo - criar arquivo para parse de php e outro para javascript
 * 
 * @author Jeferson Belmiro <jeferson.belmiro@gmail.com>
 * @license MIT
 */
class FileParser {

  protected $code;
  protected $tokenizer;
  protected $pathFile;
  protected $pathRequire;
  protected $totalLines = 0;

  protected $classes = array();
  protected $functions = array();
  protected $requires = array();
  protected $declaring = array();
  protected $constants = array();

  protected $log = "";
  protected $currentClassName;
  protected $brackets;
  protected $typeRequire = array(T_REQUIRE, T_REQUIRE_ONCE, T_INCLUDE, T_INCLUDE_ONCE);

  /**
   * @todo - adicionar .js 
   */
  const FILE_PATTERN = '/\b(?P<files>[\/\w-.]+\.php)\b/mi';

  public function __construct($pathFile, $pathProject = '/var/www/dbportal_prj/') {

    if ( !file_exists($pathFile) ) {
      throw new Exception("File {$pathFile} not exists.");
    }

    $this->pathFile = $pathFile;
    $this->pathRequire = dirname($pathFile) . '/';
    $this->pathProject = $pathProject;

    $file = fopen($pathFile, "r"); 

    while ( !feof($file) ) { 

      $this->code .= fgets($file, 4096); 
      $this->totalLines++;
    } 

    fclose($file); 

    if ( empty($this->code) ) {
      throw new Exception("File {$pathFile} is empty.");
    }

    $this->tokenizer = new Tokenizer($this->code);
    $this->parse();
  }

  public function parse() {

    $tokenizer = $this->tokenizer;
    $this->rewind();

    while ($tokenizer->valid()) {

      $token = $tokenizer->current();

      if ($token->isOpeningBrace()) {
        $this->brackets++;
      }

      if ($token->isClosingBrace()) {
        $this->brackets--;
      }

      if ( in_array($token->getValue(), $this->typeRequire) ) {

        $this->parseRequire();
        continue;
      }

      if ( $token->getValue() === T_NEW ) {

        $this->parseDeclaring();
        continue;
      }

      if ( $token->getValue() === T_CLASS ) {

        $this->parseClass();
        continue;
      }

      if ( $token->getValue() === T_FUNCTION ) {

        $this->parseFunction();
        continue;
      }

      if ( $token->getValue() === T_CONST) {

        $this->parseConstant();
        continue;
      }

      if ( $token->getValue() === T_STRING && strtolower($token->getCode()) === 'define' ) {

        $this->parseConstantDefined();
        continue;
      }

      if ( $token->getValue() === T_CONSTANT_ENCAPSED_STRING || $token->getValue() === T_ENCAPSED_AND_WHITESPACE ) {

        $this->parseString();
        continue;
      }

      if ( $token->getValue() === T_DOUBLE_COLON) {
        
        $this->parseStatic();
        continue;
      }

      if ( $token->getValue() === T_INLINE_HTML ) {

        $this->parseHTML();
        continue;
      }

      $tokenizer->next();
    }
    
  }

  public function rewind() {

    $this->tokenizer->rewind();
    $this->brackets = 0;
  }

  public function findToken($tokenName, $offset, $seek = true) {

    $find = array(';', $tokenName);

    if (is_array($tokenName)) {

      $find = $tokenName;
      array_unshift($find, ';');
    }

    $indexBeforeFind = $this->tokenizer->key();
    $index = $this->tokenizer->findToken($find, $offset);

    if (!$index) {
      return false;
    }

    $token = $this->tokenizer->offsetGet($index);

    if ($seek) {

      if ($offset > 0) {
        $this->tokenizer->seek($index + 1);
      } else {
        $this->tokenizer->seek($indexBeforeFind + 1);
      }
    }

    if ($token->getCode() == ';') {
      return false;
    }

    return $token; 
  }

  public function findTokenForward($tokenName) {
    return $this->findToken($tokenName, $this->tokenizer->key());
  }

  public function findTokenBackward($tokenName) {
    return $this->findToken($tokenName, $this->tokenizer->key() * -1);
  }

  public function clearEncapsedString($string) {
    return str_replace(array('"', "'"), '', $string);
  }

  public function getFileName($string) {

    $found = false;
    foreach (array('.php', '.js') as $extension) {

      if ( strpos($string, $extension) !== false ) {

        $found = true;
        break;
      }
    } 

    if (!$found) {
      return false;
    }

    preg_match_all(FileParser::FILE_PATTERN, $string, $matches);
    return $matches['files'];
  }

  public function parseRequire() {

    $current = $this->tokenizer->key();
    $token = $this->findTokenForward(array(T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE));

    if ( !$token ) {
      return false; 
    }
    
    $line = $token->getLine();
    $require = $this->clearEncapsedString($token->getCode());
    $requireFile = $this->pathRequire . $require; 

    if ( !file_exists($requireFile) ) {
      $requireFile = $this->pathProject . $require; 
    } 

    $requireFile = realpath($requireFile);

    if ( empty($requireFile) ) {

      $this->log .= "\n ----------------------------------------------------------------------------------------------------\n";
      $this->log .= " FileParser::parseRequire(): " . $this->pathFile . " : " . $line;
      $this->log .= "\n - Arquivo de require não encontrado: ";
      $this->log .= "\n " . $this->pathRequire . $require ."  || " . $this->pathProject . $require;
      $this->log .= "\n ----------------------------------------------------------------------------------------------------\n";
      return false;
    }

    $this->requires[] = array('line' => $line, 'file' => $requireFile); 
    return true;
  }

  public function parseDeclaring() {

    $token = $this->findTokenForward(T_STRING);

    if ( !$token ) {
      return false; 
    }

    if ( in_array($token->getCode(), array('stdClass', 'Exception')) ) {
      return false;
    } 

    $this->declaring[] = array('line' => $token->getLine(), 'class' => $token->getCode());
    return true;
  }

  public function parseClass() {

    $token = $this->findTokenForward(T_STRING);

    if ( !$token ) {
      return false; 
    }

    $this->currentClassName = $token->getCode();
    $this->classes[$this->currentClassName] = array('line' => $token->getLine(), 'method' => array(), 'constant' => array());
  }

  public function parseFunction() {

    $token = $this->findTokenForward(T_STRING);

    if ( !$token ) {
      return false; 
    }

    if ( $this->brackets === 0 ) {
      $this->currentClassName = null;
    }

    if ( empty($this->currentClassName) ) {

      $this->functions[] = array('line' => $token->getLine(), 'function' => $token->getCode());
      return;
    }

    $this->classes[$this->currentClassName]['method'][] = array('line' => $token->getLine(), 'function' => $token->getCode());
  }

  public function parseConstant() {
    
    $token = $this->findTokenForward(T_STRING);

    if ( !$token ) {
      return false; 
    }

    if ( empty($this->currentClassName) ) {

      $this->constants[] = array('line' => $token->getLine(), 'name' => $token->getCode());
      return true;
    }

    $this->classes[$this->currentClassName]['constant'][] = array('line' => $token->getLine(), 'name' => $token->getCode());
    return true; 
  }

  public function parseConstantDefined() {

    $token = $this->findTokenForward(T_STRING);

    if ( !$token ) {
      return false; 
    }

    $this->constants[] = array('line' => $token->getLine(), 'name' => $this->clearEncapsedString($token->getCode()));
    return true; 
  }

  public function parseString() {

    $token = $this->tokenizer->current();
    $string = $token->getCode();

    $this->tokenizer->next();

    $line = $token->getLine();
    $files = $this->getFileName($string);

    if ( empty($files) ) {
      return false;
    }

    foreach ( $files as $require ) {

      $requireFile = $this->pathRequire . $require; 

      if ( !file_exists($requireFile) ) {
        $requireFile = $this->pathProject . $require; 
      } 

      $requireFile = realpath($requireFile);

      if ( empty($requireFile) ) {

        $this->log .= "\n ----------------------------------------------------------------------------------------------------\n";
        $this->log .= " FileParser::parseString(): " .$this->pathFile . " : " . $line;
        $this->log .= "\n - Arquivo de require não encontrado : ";
        $this->log .= "\n " . $this->pathRequire . $require ."  || " . $this->pathProject . $require;
        $this->log .= "\n ----------------------------------------------------------------------------------------------------\n";
        return false;
      }

      $this->requires[] = array('line' => $line, 'file' => $requireFile);
    }

  }

  public function parseStatic() {

    // echo "\n\n", str_repeat('-', 80), "\n";
    // print_r($this->tokenizer->current());
    // $this->tokenizer->prev();
    // print_r($this->tokenizer->current());
    // $this->tokenizer->next();
    // $this->tokenizer->next();
    // print_r($this->tokenizer->current());
    // echo "\n", str_repeat('-', 80), "\n";
    // echo "\n- current: " . $this->tokenizer->key(), "\n";

    $token = $this->findTokenBackward(T_STRING);

    // @todo - verificar se é metodo ou constant    
    //$value = $this->parseNext(T_STRING); 
    
    if (!$token) {
      return false;
    }

    $ignore = array('SELF', '__CLASS__', strtoupper($this->currentClassName));

    if (in_array(strtoupper($token->getCode()), $ignore)) {
      return false;
    }

    $this->declaring[] = array('line' => $token->getLine(), 'class' => $token->getCode());
    return true;
  }

  public function parseHTML() {

    $token = $this->tokenizer->current();
    $string = $token->getCode();
    $lines = explode("\n", $token->getCode());
    $currentLine = $token->getLine();

    $this->tokenizer->next();

    foreach ($lines as $contentLine) {

      $files = $this->getFileName($contentLine);

      if ( empty($files) ) {

        $currentLine++;
        continue;
      }

      foreach ( $files as $require ) {

        $requireFile = $this->pathRequire . $require; 

        if ( !file_exists($requireFile) ) {
          $requireFile = $this->pathProject . $require; 
        } 

        $requireFile = realpath($requireFile);

        if ( empty($requireFile) ) {

          $this->log .= "\n ----------------------------------------------------------------------------------------------------\n";
          $this->log .= " - Arquivo de require não encontrado: " . $this->pathFile . " :" . $require;
          $this->log .= "\n   FileParser::parseHTML(): ";
          $this->log .= "\n   " . $this->pathRequire . $require ."  || " . $this->pathProject . $require;
          $this->log .= "\n ----------------------------------------------------------------------------------------------------\n";
          return false;
        }

        $this->requires[] = array('line' => $currentLine, 'file' => $requireFile);
      }

      $currentLine++;
    } 
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

  public function getConstants() {
    return $this->constants;
  }

  public function getLog() {
    return $this->log;
  }

  public function getTotalLines() {
    return $this->totalLines;
  }

}

// $file = new FileParser('/var/www/dbportal_prj/model/dataManager.php');
// $file = new FileParser('/var/www/dbportal_prj/pes2_cadferiasmes001.php');
// $file = new FileParser('/var/www/dbportal_prj/libs/db_stdlib.php');
// 
// echo "\ncontants: \n";
// print_r($file->getConstants());
// echo "\nclasses: \n";
// print_r($file->getClasses());
// echo "\nfunctions: \n";
// print_r($file->getFunctions());
// echo "\nrequires: \n";
// print_r($file->getRequires());
// echo "\ndeclaring: \n";
// print_r($file->getDeclaring());
// echo "\nlog: \n";
// print_r($file->getLog());
