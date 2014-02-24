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

  protected $lines = array();
  protected $totalLines = 0;

  protected $classes = array();
  protected $functions = array();
  protected $requires = array();
  protected $constants = array();

  protected $classesUsed = array(); // @todo - guardar metodos igual $classes
  protected $constantsUsed = array();
  protected $functionsUsed = array();

  protected $internalFunctionsUsed = array();
  protected $internalConstantsUsed = array();

  protected $externalClassesUsed = array(); // @todo - guardar metodos igual $classes
  protected $externalConstantsUsed = array();
  protected $externalFunctionsUsed = array();
  
  public function __construct($pathFile) {

    if (!file_exists($pathFile)) {
      throw new Exception("File {$pathFile} not exists.");
    }

    $this->pathFile = $pathFile;

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
    $code = null;

    $this->parse();
    $this->parseExternalInternalUsed();
  }

  public function parse() {

    $tokenizer = $this->tokenizer;
    $this->tokenizer->rewind();

    while ($tokenizer->valid()) {

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

        case T_EMPTY :
        case T_ARRAY :
        case T_EVAL  :
        case T_EXIT  :
        case T_ISSET :
        case T_LIST  :
        case T_PRINT :
        case T_UNSET :
          $this->internalFunctionsUsed[] = new ParseData(
            $this->tokenizer->current()->getCode(), 
            $this->tokenizer->current()->getLine()
          ); 
        break;
        
        case T_CONST :
          $this->parseConstant();
        break;
        
        case T_STRING :
          $this->parseString();
        break;
      }

      $tokenizer->next();
    }
    
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

  public function parseRequire() {

    $index = $this->findTokenForward(array(T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE));

    if (!$index) {
      return false; 
    }

    $this->tokenizer->seek($index);
    $token = $this->tokenizer->offsetGet($index);
    $line = $token->getLine();
    $require = $this->clearEncapsedString($token->getCode());

    $this->requires[] = new ParseData($require, $line); 
    return true;
  }

  public function parseClassesUsed() {

    $index = $this->findTokenForward(T_STRING);

    if (!$index) {
      return false; 
    }

    $this->tokenizer->seek($index);
    $token = $this->tokenizer->offsetGet($index);

    if (in_array(strtoupper($token->getCode()), array('STDCLASS', 'EXCEPTION'))) {
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

    $this->classes[$class] = new ParseDataClass($class, $startLine, $endLine);
    return true;
  }

  public function getClassRange($startLine, $endLine = 0) {

    foreach ($this->classes as $classData) {

      if ($classData->getStartLine() > $startLine || $classData->getEndLine() < $startLine) {
        continue;
      }

      if ( !empty($endLine) ) {

        if ($classData->getStartLine() > $endLine || $classData->getEndLine() < $endLine) {
          continue;
        }
      }

      return $classData->getValue();
    }
  }

  public function parseFunction() {

    $index = $this->findTokenForward(T_STRING);

    if (!$index) {
      return false; 
    }

    $this->tokenizer->seek($index);

    $token        = $this->tokenizer->offsetGet($index);
    $startLine    = $token->getLine();
    $endLine      = $this->parseEndLine($startLine);
    $function     = $token->getCode();
    $className    = $this->getClassRange($startLine, $endLine);
    $functionData = new ParseData($function, $startLine, $endLine);

    if ( !empty($className) ) {

      $this->classes[$className]->addMethod($functionData);
      return true;
    }

    $this->functions[] = $functionData;
    return true;
  }

  public function parseConstant() {
    
    $index = $this->findTokenForward(T_STRING);

    if (!$index) {
      return false; 
    }

    $this->tokenizer->seek($index);

    $token        = $this->tokenizer->offsetGet($index);
    $startLine    = $token->getLine();
    $constant     = $token->getCode();
    $constantData = new ParseData($constant, $startLine);
    $className    = $this->getClassRange($startLine);

    if ( !empty($className) ) {

      $this->classes[$className]->addConstant($constantData);
      return true;
    }

    $this->constants[] = $constantData;
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

  // @todo - descobrir se metodo, propriedade ou constant
  public function parseStatic() {

    $token = $this->tokenizer->current();
    $index = $this->findTokenForward(';');

    if ($index) {
      $this->tokenizer->seek($index);
    }

    if (in_array(strtoupper($token->getCode()), array('SELF', '__CLASS__'))) {
      return false;
    }

    $this->classesUsed[] = new ParseData($token->getCode(), $token->getLine());
    return true;
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
     * Method or property 
     */
    if ($this->findTokenBackward(T_OBJECT_OPERATOR)) {
      return false;
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
   * @todo - criar classe para guardar lista dos parses, ParseDataList extends ParseData
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
      'classes'       => $this->classes,
      'functions'     => $this->functions,
      'classesUsed'   => $this->classesUsed,
      'constants'     => $this->constants,
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

  function parseExternalInternalUsed() {

    foreach ($this->classesUsed as $classData) {
    
      if (!in_array($classData->getValue(), array_keys($this->classes))) {
        $this->externalClassesUsed[] = $classData;
      }
    }

    $definedConstants = get_defined_constants(true);
    $internalConstants = array();

    foreach ($definedConstants as $category => $constants) {

      if ($category == 'user') {
        continue;
      }

      foreach ($constants as $constantName => $constantValue) {
        $internalConstants[] = $constantName;
      }
    }

    foreach ($this->constantsUsed as $constantData) {

      if (in_array($constantData->getValue(), $internalConstants)) {

        $this->internalConstantsUsed[] = $constantData;
        continue;
      }
    
      if (!in_array($constantData->getValue(), array_keys($this->constants))) {
        $this->externalConstantsUsed[] = $constantData;
      }
    }

    $definedFunctions = get_defined_functions();
    $internalFunctions = array_map('strtolower', $definedFunctions['internal']);

    foreach ($this->functionsUsed as $functionUsedData) {
    
      if (in_array(strtolower($functionUsedData->getValue()), $internalFunctions)) {

        $this->internalFunctionsUsed[] = $functionUsedData;
        continue;
      }

      $found = false;

      foreach ($this->functions as $functionData) {

        if (strtoupper($functionUsedData->getValue()) == strtoupper($functionData->getValue())) {
          $found = true;
        }
      }

      if (!$found) {
        $this->externalFunctionsUsed[] = $functionUsedData;
      }
    }
  }

  public function getFunctionsArguments() {

    $content = file_get_contents($this->pathFile);

    preg_match_all("/(function )(\S*\(\S*\))/", $content, $matches);

    foreach($matches[2] as $match) {
      $function[] = trim($match);
    }

    natcasesort($function);
    return $function;
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

  public function getExternalClassesUsed() {
    return $this->externalClassesUsed;
  }

  public function getConstants() {
    return $this->constants;
  }

  public function getConstantsUsed() {
    return $this->constantsUsed;
  }

  public function getExternalConstantsUsed() {
    return $this->externalConstantsUsed;
  }

  public function getInternalConstantsUsed() {
    return $this->internalConstantsUsed;
  }

  public function getFunctionsUsed() {
    return $this->functionsUsed;
  }
  
  public function getExternalFunctionsUsed() {
    return $this->externalFunctionsUsed;
  }

  public function getInternalFunctionsUsed() {
    return $this->internalFunctionsUsed;
  }

  public function getTotalLines() {
    return $this->totalLines;
  }

}
