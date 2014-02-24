<?php
require_once __DIR__ . '/FileParser.php';

class DBFileParser extends FileParser {
  
  protected $pathRequire;
  protected $log = "";

  public function __construct($pathFile, $pathProject = '/var/www/dbportal_prj/') {

    parent::__construct($pathFile);

    $this->pathRequire = dirname($pathFile) . DIRECTORY_SEPARATOR;

    if (empty($pathProject)) {
      $pathProject = $this->pathRequire;
    }

    $this->pathProject = rtrim($pathProject, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

    $this->processar();
    $this->processarRequire();
  }

  public function processar() {

    $tokenizer = $this->tokenizer;
    $this->tokenizer->rewind();

    while($tokenizer->valid()) {

      $oToken = $tokenizer->current();

      if ($oToken->is(T_STRING) && $oToken->is('db_utils')) {
        $this->processarGetDao();
      }

      switch($oToken->getValue()) {

        case T_CONSTANT_ENCAPSED_STRING :
        case T_ENCAPSED_AND_WHITESPACE :
          $this->parseEncapsedString();
        break;

        case T_INLINE_HTML :
          $this->parseHTML();
        break;

      }

      $tokenizer->next();
    }

  } 

  public function processarGetDao() {
    
    $iProximoToken = $this->findTokenForward(T_STRING);

    if (!$iProximoToken || $this->tokenizer->offsetGet($iProximoToken)->getCode() !== 'getDao') {
      return false;
    }

    $iIndex = $this->findTokenForward(array(T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE));
    
    if (!$iIndex) {
      return false;
    }

    $this->tokenizer->seek($iIndex);
    $oToken = $this->tokenizer->offsetGet($iIndex);
    $sClasse = 'cl_' . $this->clearEncapsedString($oToken->getCode());

    $this->classesUsed[] = new ParseData($sClasse, $oToken->getLine());
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
      $this->requires[] = new ParseData($require, $line);
    }

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
        $this->requires[] = new ParseData($require, $currentLine);
      }

      $currentLine++;
    } 
  }

  public function processarRequire() {

    $requires = $this->requires;
    $this->requires = array();

    foreach ($requires as $requireData) {

      $require = $requireData->getValue();
      $requireFile = $this->pathRequire . $require; 

      if ( !file_exists($requireFile) ) {
        $requireFile = $this->pathProject . $require; 
      } 

      $requireFile = realpath($requireFile);

      if ( empty($requireFile) ) {

        $this->log .= "\n ----------------------------------------------------------------------------------------------------\n";
        $this->log .= " FileParser::parseRequire(): " . $this->pathFile . " : " . $requireData->getEndLine();
        $this->log .= "\n - Arquivo de require não encontrado: ";
        $this->log .= "\n " . $this->pathRequire . $require ."  || " . $this->pathProject . $require;
        $this->log .= "\n ----------------------------------------------------------------------------------------------------\n";
        continue;
      }

      $this->requires[] = new ParseData($requireFile, $requireData->getStartLine(), $requireData->getEndLine());
    }

  }

  public function getFileName($string) {

    if ( strpos($string, '.php') === false ) {
      return false;
    }

    preg_match_all('/\b(?P<files>[\/\w-.]+\.php)\b/mi', $string, $matches);
    return $matches['files'];
  } 

  public function getLog() {
    return $this->log;
  }

}

// $file = new DBFileParser('/var/www/dbportal_prj/model/dataManager.php');
// $file = new DBFileParser('/var/www/dbportal_prj/pes2_cadferiasmes001.php');
// $file = new DBFileParser('/var/www/dbportal_prj/libs/db_stdlib.php');
$file = new DBFileParser('/var/www/dbportal_prj/libs/db_stdlibwebseller.php');
 
debug("requires:", $file->getRequires());
debug("classes:", $file->getClasses());
debug("classes used:", $file->getClassesUsed());
debug("external classes used:", $file->getExternalClassesUsed());

debug("functions:", $file->getFunctions());
debug("function used:", $file->getFunctionsUsed());
debug("external function used:", $file->getExternalFunctionsUsed());
debug("internal function used:", $file->getInternalFunctionsUsed());

debug("contants:", $file->getConstants());
debug("constants used:", $file->getConstantsUsed());
debug("external constants used:", $file->getExternalConstantsUsed());
debug("internal constants used:", $file->getInternalConstantsUsed());

debug("log:", $file->getLog());

// debug("range 4021:");
// print_r($file->getDataLine(4021));

function debug($texto, $array) {

  echo "\n\n", str_repeat("-", 80), "\n", $texto, "\n", str_repeat("-", 80), "\n";
  print_r($array);
}
