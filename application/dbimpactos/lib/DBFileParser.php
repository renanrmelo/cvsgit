<?php
require_once __DIR__ . '/FileParser.php';

class DBFileParser extends FileParser {
  
  public function __construct($pathFile, $pathProject = '/var/www/dbportal_prj/') {

    parent::__construct($pathFile, $pathProject);
    $this->processar();
  }

  public function processar() {

    $tokenizer = $this->tokenizer;
    $this->rewind();

    while($tokenizer->valid()) {

      $oToken = $tokenizer->current();

      if ($oToken->is(T_STRING) && $oToken->is('db_utils')) {
        $this->processarGetDao();
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

    $this->declaring[] = new ParseData($sClasse, $oToken->getLine());
    return true;
  }

}

// $file = new FileParser('/var/www/dbportal_prj/model/dataManager.php');
// $file = new FileParser('/var/www/dbportal_prj/pes2_cadferiasmes001.php');
$file = new DBFileParser('/var/www/dbportal_prj/libs/db_stdlib.php');
 
// echo "\ncontants: \n";
// print_r($file->getConstants());
// echo "\nclasses: \n";
// print_r($file->getClasses());
// echo "\nfunctions: \n";
// print_r($file->getFunctions());
// echo "\nrequires: \n";
// print_r($file->getRequires());
echo "\nclasses used: \n";
print_r($file->getClassesUsed());
echo "\nfunction used: \n";
print_r($file->getFunctionsUsed());
echo "\nconstants used: \n";
print_r($file->getConstantsUsed());
echo "\nlog: \n";
print_r($file->getLog());
// echo "\n\nrange 50:\n";
// print_r($file->getDataLine(50));
