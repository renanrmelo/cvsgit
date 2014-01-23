<?php
require_once __DIR__ . '/ParseData.php';

class ParseDataClass extends ParseData {

  private $methods = array();
  private $constants = array();

  public function addMethod($method) {
    $this->methods[] = $method;
  }

  public function addConstant($constant) {
    $this->constants[] = $constant;
  }

  public function getMethods() {
    return $this->methods;
  }

  public function getConstants() {
    return $this->constants;
  }

}
