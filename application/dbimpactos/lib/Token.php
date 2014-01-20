<?php

/**
 * Token
 *
 * @author  Jeferson Belmiro - jeferson.belmiro@gmail.com
 * @license MIT
 * @see     http://us2.php.net/manual/en/tokens.php
 */
class Token {

  /**
   * name
   * 
   * @var string
   * @access protected
   */
  protected $name;

  /**
   * value
   * 
   * @var integer
   * @access protected
   */
  protected $value;

  /**
   * code
   * 
   * @var string
   * @access protected
   */
  protected $code;

  /**
   * line
   * 
   * @var integer
   * @access protected
   */
  protected $line;

  /**
   * Constructs a token object.
   *
   * @param mixed $token Either a literal string token or an array of token data as returned by get_token_all()
   */
  public function __construct($token) {

    if (is_string($token)) {

      $this->name  = null;
      $this->value = null;
      $this->code  = $token;
      $this->line  = null;
      return true;
    }

    if (is_array($token) && in_array(count($token), array(2, 3))) {

      $this->name  = token_name($token[0]);
      $this->value = $token[0];
      $this->code  = $token[1];
      $this->line  = isset($token[2]) ? $token[2] : null;
      return true;
    }

    throw new InvalidArgumentException('The token was invalid.');
  }

  /**
   * Get the token name.
   *
   * @return string - The token name. Always null for literal tokens.
   */
  public function getName() {
    return $this->name;
  }

  /**
   * Get the token's integer value. Always null for literal tokens.
   *
   * @return integer - The token value.
   */
  public function getValue() {
    return $this->value;
  }

  /**
   * Get the token's PHP code as a string.
   *
   * @return string
   */
  public function getCode() {
    return $this->code;
  }

  /**
   * Get the line where the token was defined. Always null for literal tokens.
   *
   * @return integer
   */
  public function getLine() {
    return $this->line;
  }

  /**
   * Determines whether the token is an opening brace.
   *
   * @return boolean True if the token is an opening brace.
   */
  public function isOpeningBrace() {
    return ($this->code === '{' || $this->name === 'T_CURLY_OPEN' || $this->name === 'T_DOLLAR_OPEN_CURLY_BRACES');
  }

  /**
   * Determines whether the token is an closing brace.
   *
   * @return boolean True if the token is an closing brace.
   */
  public function isClosingBrace() {
    return ($this->code === '}');
  }

  /**
   * Determines whether the token is an opening parenthsesis.
   *
   * @return boolean True if the token is an opening parenthsesis.
   */
  public function isOpeningParenthesis() {
    return ($this->code === '(');
  }

  /**
   * Determines whether the token is an closing parenthsesis.
   *
   * @return boolean True if the token is an closing parenthsesis.
   */
  public function isClosingParenthesis() {
    return ($this->code === ')');
  }

  /**
   * Determines whether the token is a literal token.
   *
   * @return boolean True if the token is a literal token.
   */
  public function isLiteralToken() {
    return ($this->name === null && $this->code !== null);
  }

  /**
   * Determines whether the token's integer value or code is equal to the specified value.
   *
   * @param mixed $value The value to check.
   * @return boolean True if the token is equal to the value.
   */
  public function is($value) {
    return ($this->code === $value || $this->value === $value);
  }

  /**
   * Magic getter.
   *
   * @param string $key The property name.
   * @return mixed The property value.
   * @throws OutOfBoundsException
   */
  public function __get($key) {

    if (!property_exists(__CLASS__, $key)) {
      throw new OutOfBoundsException("The property \"{$key}\" does not exist in Token.");
    }

    return $this->{$key};
  }

  /**
   * Magic setter.
   *
   * @param string $key The property name.
   * @param mixed $value The property's new value.
   * @throws OutOfBoundsException
   */
  public function __set($key, $value) {

    if (!property_exists(__CLASS__, $key)) {
      throw new \OutOfBoundsException("The property \"{$key}\" does not exist in Token.");
    }

    $this->{$key} = $value;
  }

  /**
   * Magic isset.
   *
   * @param string $key The property name.
   * @return boolean Whether or not the property is set.
   */
  public function __isset($key) {
    return isset($this->{$key});
  }

  /**
   * Magic tostring.
   *
   * @return string The code.
   */
  public function __toString() {
    return $this->code;
  }

} 
