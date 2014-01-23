<?php

class ParseData {

  private $value;
  private $line;

  public function __construct($value, $line) {

    $this->value = $value;
    $this->line = $line;
  }

  public function getValue() {
    return $this->value;
  }
  
  public function getLine() {
    return $this->line;
  }

}
