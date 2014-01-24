<?php

class ParseData {

  private $value;
  private $startLine;
  private $endLine;

  public function __construct($value, $startLine = 0, $endLine = 0) {

    $this->value = $value;
    $this->startLine = $startLine;
    $this->endLine = $endLine;

    if (empty($endLine)) {
      $this->endLine = $startLine;
    }
  }

  public function getValue() {
    return $this->value;
  }
  
  public function getStartLine() {
    return $this->startLine;
  }

  public function getEndLine() {
    return $this->endLine;
  }

}
