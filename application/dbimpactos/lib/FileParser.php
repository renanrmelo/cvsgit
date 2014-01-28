<?php
require_once __DIR__ . '/Tokenizer.php';
require_once __DIR__ . '/ParseData.php';
require_once __DIR__ . '/ParseDataClass.php';

/**
 * File Parser
 *
 * @todo - criar arquivo para parse de php e outro para javascript
 * 
 * @author Jeferson Belmiro <jeferson.belmiro@gmail.com>
 * @license MIT
 */
class FileParser {

  protected $tokenizer;
  protected $pathFile;
  protected $pathRequire;

  protected $lines = array();
  protected $totalLines = 0;

  protected $classes = array();
  protected $functions = array();
  protected $requires = array();
  protected $classesUsed = array(); // @todo - guardar metodos igual $classes
  protected $constants = array();

  protected $constantsUsed = array();
  protected $functionsUsed = array();

  protected $log = "";
  protected $currentClassName;
  protected $braces;

  public function __construct($pathFile, $pathProject = null) {

    if (!file_exists($pathFile)) {
      throw new Exception("File {$pathFile} not exists.");
    }

    $this->pathFile = $pathFile;
    $this->pathRequire = dirname($pathFile) . DIRECTORY_SEPARATOR;

    if (empty($pathProject)) {
      $pathProject = $this->pathRequire;
    }

    $this->pathProject = rtrim($pathProject, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

    $file = fopen($pathFile, "r"); 
    $code = '';

    while (!feof($file)) { 

      $line = fgets($file, 4096);  
      $code .= $line; 
      $this->totalLines++;
      $this->lines[$this->totalLines] = $line;
    } 

    fclose($file); 

    if ( empty($code) ) {
      throw new Exception("File {$pathFile} is empty.");
    }

    $this->tokenizer = new Tokenizer($code);
    $this->parse();
    $code = null;
  }

  public function parse() {

    $tokenizer = $this->tokenizer;
    $this->rewind();

    while ($tokenizer->valid()) {

      if($tokenizer->current()->isOpeningBrace()) {

        $this->braces++;
        $this->tokenizer->next();
        continue;
      }
      
      if ($tokenizer->current()->isClosingBrace()) {

        $this->braces--;
        $this->tokenizer->next();
        continue;
      }

      switch ($tokenizer->current()->getValue()) {

        case T_REQUIRE :
        case T_INCLUDE : 
        case T_REQUIRE_ONCE :
        case T_INCLUDE_ONCE :
          $this->parseRequire();
        break;

        case T_NEW :
          $this->parseClassesUsed();
        break;
          
        case T_CLASS :
          $this->parseClass();
        break;

        case T_FUNCTION :
          $this->parseFunction();
        break;
        
        case T_CONST :
          $this->parseConstant();
        break;
        
        case T_CONSTANT_ENCAPSED_STRING :
        case T_ENCAPSED_AND_WHITESPACE :
          $this->parseEncapsedString();
        break;
        
        case T_INLINE_HTML :
          $this->parseHTML();
        break;
        
        case T_STRING :
          $this->parseString();
        break;
      }

      $tokenizer->next();
    }
    
  }

  public function rewind() {

    $this->tokenizer->rewind();
    $this->braces = 0;
  }

  public function getNextToken($indexSeek = 1) {

    $tokenizer = clone $this->tokenizer;
    $tokenizer->seek($tokenizer->key() + $indexSeek);

    if ($tokenizer->current()->is(T_WHITESPACE)) {
      $tokenizer->next();
    } 

    return $tokenizer->current();
  }

  public function getPreviousToken($indexSeek = 1) {

    $tokenizer = clone $this->tokenizer;
    $tokenizer->seek($tokenizer->key() - $indexSeek);

    if ($tokenizer->current()->is(T_WHITESPACE)) {
      $tokenizer->prev();
    } 

    return $tokenizer->current();
  }

  public function findToken($find, $offset = 0, $tokenStop = ';', $direction = Tokenizer::FIND_TOKEN_FORWARD) {

    $tokens = array($tokenStop, $find);

    if (is_array($find)) {

      $tokens = $find;
      array_unshift($tokens, $tokenStop);
    }

    $index = $this->tokenizer->findToken($tokens, $offset, $direction);

    if (!$index) {
      return false;
    }

    if ($find !== $tokenStop && $this->tokenizer->offsetGet($index)->is($tokenStop)) {
      return false;
    }

    return $index; 
  }

  public function findTokenForward($find, $tokenStop = ';') {
    return $this->findToken($find, $this->tokenizer->key(), $tokenStop, Tokenizer::FIND_TOKEN_FORWARD);
  }

  public function findTokenBackward($find, $tokenStop = ';') {
    return $this->findToken($find, $this->tokenizer->key(), $tokenStop, Tokenizer::FIND_TOKEN_BACKWARD);
  }

  public function clearEncapsedString($string) {
    return str_replace(array('"', "'"), '', $string);
  }

  public function getFileName($string) {

    if ( strpos($string, '.php') === false ) {
      return false;
    }

    preg_match_all('/\b(?P<files>[\/\w-.]+\.php)\b/mi', $string, $matches);
    return $matches['files'];
  }

  public function parseRequire() {

    $index = $this->findTokenForward(array(T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE));

    if (!$index) {
      return false; 
    }

    $this->tokenizer->seek($index);
    $token = $this->tokenizer->offsetGet($index);
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

    $this->requires[] = new ParseData($requireFile, $line); 
    return true;
  }

  public function parseClassesUsed() {

    $index = $this->findTokenForward(T_STRING);

    if (!$index) {
      return false; 
    }

    $this->tokenizer->seek($index);
    $token = $this->tokenizer->offsetGet($index);

    if (in_array(strtolower($token->getCode()), array('stdclass', 'exception'))) {
      return false;
    } 

    $this->classesUsed[] = new ParseData($token->getCode(), $token->getLine());
    return true;
  }

  public function parseClass() {

    $index = $this->findTokenForward(T_STRING);

    if (!$index) {
      return false; 
    }

    $this->tokenizer->seek($index);

    $token     = $this->tokenizer->offsetGet($index);
    $startLine = $token->getLine();
    $endLine   = $this->parseEndLine($startLine);
    $class     = $token->getCode();

    $this->currentClassName = $class;
    $this->classes[$class] = new ParseDataClass($class, $startLine, $endLine);
    return true;
  }

  public function parseFunction() {

    $index = $this->findTokenForward(T_STRING);

    if (!$index) {
      return false; 
    }

    $this->tokenizer->seek($index);

    $token     = $this->tokenizer->offsetGet($index);
    $startLine = $token->getLine();
    $endLine   = $this->parseEndLine($startLine);
    $function  = $token->getCode();

    if ($this->braces === 0) {
      $this->currentClassName = null;
    }
    
    if ( empty($this->currentClassName) ) {

      $this->functions[] = new ParseData($function, $startLine, $endLine);
      return true;
    }

    $this->classes[$this->currentClassName]->addMethod(new ParseData($function, $startLine, $endLine));
    return true;
  }

  public function parseConstant() {
    
    $index = $this->findTokenForward(T_STRING);

    if (!$index) {
      return false; 
    }

    $this->tokenizer->seek($index);
    $token = $this->tokenizer->offsetGet($index);

    if ( empty($this->currentClassName) ) {

      $this->constants[] = new ParseData($token->getCode(), $token->getLine());
      return true;
    }

    $this->classes[$this->currentClassName]->addConstant(new ParseData($token->getCode(), $token->getLine()));
    return true; 
  }

  public function parseConstantDefined() {

    $index = $this->findTokenForward(array(T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE));

    if (!$index) {
      return false; 
    }

    $this->tokenizer->seek($index);
    $token = $this->tokenizer->offsetGet($index);

    $this->constants[] = new ParseData($this->clearEncapsedString($token->getCode()), $token->getLine());
    return true; 
  }

  public function parseEncapsedString() {

    $token = $this->tokenizer->current();
    $string = $token->getCode();
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
        $this->log .= " FileParser::parseEncapsedString(): " .$this->pathFile . " : " . $line;
        $this->log .= "\n - Arquivo de require não encontrado : ";
        $this->log .= "\n " . $this->pathRequire . $require ."  || " . $this->pathProject . $require;
        $this->log .= "\n ----------------------------------------------------------------------------------------------------\n";
        return false;
      }

      $this->requires[] = new ParseData($requireFile, $line);
    }

  }

  // @todo - descobrir se metodo ou constant
  public function parseStatic() {

    $token = $this->tokenizer->current();
    $ignore = array('SELF', '__CLASS__', strtoupper($this->currentClassName));
    $index = $this->findTokenForward(';');

    if ($index) {
      $this->tokenizer->seek($index);
    }

    if (in_array(strtoupper($token->getCode()), $ignore)) {
      return false;
    }

    $this->classesUsed[] = new ParseData($token->getCode(), $token->getLine());
    return true;
  }

  public function parseHTML() {

    $token = $this->tokenizer->current();
    $string = $token->getCode();
    $lines = explode("\n", $token->getCode());
    $currentLine = $token->getLine();

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

        $this->requires[] = new ParseData($requireFile, $currentLine);
      }

      $currentLine++;
    } 
  }

  public function parseString() {

    /**
     * Define constant
     */
    if ($this->tokenizer->current()->is('define')) {
      return $this->parseConstantDefined();
    }

    /**
     * Static method or constant 
     */
    if ($this->getNextToken()->is(T_DOUBLE_COLON)) {
      return $this->parseStatic();
    }
      
    /**
     * Function
     */
    if ($this->getNextToken()->is('(')) {
      return $this->parseFunctionUsed();
    }

    /**
     * try catch 
     */
    if ($this->findTokenBackward(T_CATCH)) {
      return false;
    }

    /**
     * Method or property 
     */
    if ($this->findTokenBackward(T_OBJECT_OPERATOR)) {
      return false;
    }

    /**
     * Constant 
     */
    return $this->parseConstantUsed();
  }

  public function parseConstantUsed() {

    $token = $this->tokenizer->current();

    if (in_array(strtoupper($token->getCode()), array('FALSE', 'TRUE', 'NULL')) ) { 
      return false;
    }

    $this->constantsUsed[] = new ParseData($token->getCode(), $token->getLine());
    return true; 
  }

  public function parseFunctionUsed() {

    $token = $this->tokenizer->current();
    $this->functionsUsed[] = new ParseData($token->getCode(), $token->getLine()); 
    return true; 
  }

  public function parseBraces() {

  }

  public function parseEndLine($startLine) {

    $braces = 0;
    $foundStart = false;

    for ($line = $startLine; $line < $this->totalLines; $line++) {

      $code = $this->lines[$line];
      $openBracesFound = mb_substr_count($code, '{');
      $closeBracesFound = mb_substr_count($code, '}');

      if ($openBracesFound > 0) {

        $foundStart = true;
        $braces += $openBracesFound; 
      }

      if ($closeBracesFound > 0) {
        $braces -= $closeBracesFound; 
      }

      if ($braces <= 0 && $foundStart) {
        return $line;
      }
    }

    return $startLine;
  }

  /**
   * Returns all data where the line is between
   *
   * @todo - criar classe para guardar lista dos parses, algo tipo ParseDataList extends ParseData
   *
   * @param integer $line
   * @access public
   * @return ParseData[]
   */
  public function getDataLine($line) {

    $dataRange = array();

    if ($line > $this->getTotalLines()) {
      return $dataRange;
    }

    $find = array(
      'classes' => $this->classes, 
      'functions' => $this->functions, 
      'classesUsed' => $this->classesUsed,
      'constants' => $this->constants,
      'constantsUsed' => $this->constantsUsed,
      'functionsUsed' => $this->functionsUsed
    );

    foreach ($find as $name => $dataFind ) {

      foreach ($dataFind as $data) {

        if ($name == 'classes') {

          /**
           * Line is not between start and end class 
           */
          if ($line < $data->getStartLine() || $line > $data->getEndLine()) {
            continue;
          }

          /**
           * New instance to save only methods that are in the range
           */
          $dataClass = new ParseDataClass($data->getValue(), $data->getStartLine(), $data->getEndLine());

          foreach($data->getMethods() as $method) {

            if ($line >= $method->getStartLine() && $line <= $method->getEndLine()) {
              $dataClass->addMethod($method);
            }
          }

          $dataRange[$name][] = $dataClass;
          continue;
        }

        if ($line >= $data->getStartLine() && $line <= $data->getEndLine()) {
          $dataRange[$name][] = $data;
        }

      }
    }

    return $dataRange;
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

  public function getClassesUsed() {
    return $this->classesUsed;
  }

  public function getConstants() {
    return $this->constants;
  }

  public function getConstantsUsed() {
    return $this->constantsUsed;
  }

  public function getFunctionsUsed() {
    return $this->functionsUsed;
  }

  public function getLog() {
    return $this->log;
  }

  public function getTotalLines() {
    return $this->totalLines;
  }

}
