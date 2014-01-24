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
        continue;
      }

      $tokenizer->next();
    }

  } 

  public function processarGetDao() {
    
    $oProximoToken = $this->findTokenForward(T_STRING);

    if (!$oProximoToken || $oProximoToken->getCode() !== 'getDao') {
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
