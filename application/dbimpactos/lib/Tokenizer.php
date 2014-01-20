<?php
require_once __DIR__ . '/Token.php';

/**
 * Tokenizer
 *
 * @author Jeferson Belmiro <jeferson.belmiro@gmail.com>
 * @license MIT
 */
class Tokenizer implements SeekableIterator, Countable, ArrayAccess, Serializable {

  /**
   * @var array The array of tokens.
   */
  protected $tokens = array();

  /**
   * @var integer The current index of the iterator through the tokens.
   */
  protected $index = 0;

  /**
   * Constructs a Tokenizer object.
   *
   * @param string | array $code The code to tokenize, or an array of Token objects.
   */
  public function __construct($code) {

    if (!function_exists('token_get_all')) {
      throw new RuntimeException('The PHP tokenizer not enabled.');
    }

    if (is_string($code)) {

      $code = trim($code);

      $this->tokens = array_map(function($token) {
        return new Token($token);
      }, @token_get_all($code));

      return true;
    }

    if (is_array($code) && isset($code[0]) && $code[0] instanceof Token) {
      return $this->tokens = $code; 
    }
    
    throw new InvalidArgumentException('The tokenizer either expects a string of code or an array of Tokens.');
 }

  /**
   * Move to the next token and return it. Returns null if there are no more tokens.
   *
   * @return Token The next token in the tokenizer.
   */
  public function getNextToken() {

    $this->next(); 
    return $this->valid() ? $this->current() : null;
  }

  /**
   * Move to the previous token and return it. Returns null if there are no more tokens.
   *
   * @return Token The previous token in the tokenizer.
   */
  public function getPreviousToken() {

    $this->prev(); 
    return $this->valid() ? $this->current() : null;
  }

  /**
   * Determines whether or not there are more tokens left.
   *
   * @return boolean True if there are more tokens left in the tokenizer.
   */
  public function hasMoreTokens() {
    return ($this->index < $this->count() - 1);
  }

  /**
   * Find a token in the tokenizer.
   *
   * @param mixed $search
   * @param integer $offset The offset to start searching from. A negative offest searches back.
   * @return integer | boolean The index of the token that has been found or false.
   */
  public function findToken($search, $offset = 0) {

    if (empty($search)) {
      throw new InvalidArgumentException('A token cannot be searched for with a null value.');
    } 
    
    if (!is_int($offset)) {
      throw new InvalidArgumentException('On offset must be specified as an integer.');
    }

    $reversed = false;
    $tokenizer = clone $this;

    if (is_scalar($search)) {
      $search = array($search);
    }

    if ($offset < 0) {

      $reversed = true;
      $offset = abs($offset);
    }

    $tokenizer->seek($offset);

    while ($tokenizer->valid()) {

      $token = $tokenizer->current();

      if (in_array($token->getCode(), $search))  {
        return $tokenizer->key();
      }

      if (in_array($token->getName(), $search))  {
        return $tokenizer->key();
      }

      if (in_array($token->getValue(), $search))  {
        return $tokenizer->key();
      }

      if ($reversed) {

        $tokenizer->prev();
        continue;
      }

      $tokenizer->next();
    }

    return false;
  }

  /**
   * Determines whether or not a token is in the tokenizer. Searches by literal token code or name
   *
   * @param string | integer $search The token's literal code or name.
   * @return boolean Whether or not the token is in the tokenizer
   */
  public function hasToken($search) {
    return (boolean) $this->findToken($search);
  }

  /**
   * Returns a new tokenizer that consists of a subset of the tokens specified by the provided range.
   *
   * @param integer $start The starting offset of the range
   * @param integer $finish The ending offset of the range
   * @return Tokenizer 
   */
  public function getTokenRange($start, $finish) {

    $tokens = array_slice($this->tokens, (integer) $start, (integer) $finish - (integer) $start); 
    return new Tokenizer($tokens);
  }

  /**
   * Prepends a tokenizer to the beginning of this tokenizer.
   *
   * @param Tokenizer $newTokens The tokenizer to prepend.
   * @return Tokenizer
   */
  public function prependTokens(Tokenizer $newTokens) {

    $this->tokens = array_merge($newTokens->asArray(), $this->tokens);
    $this->rewind();

    return $this;
  }

  /**
   * Appends a tokenizer to the beginning of this tokenizer.
   *
   * @param Tokenizer $newTokens The tokenizer to append.
   * @return Tokenizer
   */
  public function appendTokens(Tokenizer $newTokens) {

    $this->tokens = array_merge($this->tokens, $newTokens->asArray());
    $this->rewind();

    return $this;
  }

  /**
   * Get the first token.
   *
   * @return Token The first token.
   */
  public function getFirst() {

    $this->index = 0; 
    return $this->current();
  }

  /**
   * Get the last token
   *
   * @return Token
   */
  public function getLast() {

    $this->index = $this->count() - 1; 
    return $this->current();
  }

  /**
   * Returns the current token.
   *
   * @return Token
   */
  public function current() {
    return $this->tokens[$this->index];
  }

  /**
   * Move to the next token.
   */
  public function next() {
    $this->index++;
  }

  /**
   * Move to the previous token.
   */
  public function prev() {
    $this->index--;
  }

  /**
   * Return the current token's index.
   *
   * @return integer The token's index.
   */
  public function key() {
    return $this->index;
  }

  /**
   * Determines whether or not the tokenizer's index points to a token.
   *
   * @return boolean - True if the current token exists.
   */
  public function valid() {
    return array_key_exists($this->index, $this->tokens);
  }

  /**
   * Move to the first token.
   */
  public function rewind() {
    $this->index = 0;
  }

  /**
   * Move to the specified token.
   *
   * @param integer $index The index to seek to.
   */
  public function seek($index) {
    $this->index = (integer) $index;
  }

  /**
   * Determines wheter or not the specified offset exists.
   *
   * @param integer $offset The offset to check.
   * @return boolean Whether or not the offset exists.
   */
  public function offsetExists($offset) {
    return is_integer($offset) && array_key_exists($offset, $this->tokens);
  }

  /**
   * Gets the token at the specified offset.
   *
   * @param integer $offset The offset to get.
   * @return Token - The token at the offset.
   */
  public function offsetGet($offset) {
    return $this->tokens[$offset];
  }

  /**
   * Sets the token at the specified offset.
   *
   * @param integer $offset The offset to set.
   * @param Token The token to set.
   * @throws InvalidArgumentException
   */
  public function offsetSet($offset, $value) {

    if (!(is_integer($offset) && $offset >= 0 && $offset <= $this->count())) {
      throw new InvalidArgumentException('The offset must be a valid, positive integer.');
    }

    if (!$value instanceof Token) {
      throw new InvalidArgumentException('The value provided must be a token.');
    }

    $this->tokens[$offset] = $value;
  }

  /**
   * Unsets the token at the specified offset.
   *
   * @param integer $offset The offset to unset.
   */
  public function offsetUnset($offset) {

    if ($this->offsetExists($offset)) {

      unset($this->tokens[$offset]);

      $this->tokens = array_values($this->tokens);

      if (!$this->valid()) {
        $this->rewind();
      }
    }
  }

  /**
   * Get the number of tokens in the tokenizer.
   *
   * @return integer 
   */
  public function count() {
    return count($this->tokens);
  }

  /**
   * Serializes the tokenizer.
   *
   * @return string The serialized tokenizer.
   */
  public function serialize() {

    return serialize(array(
      'tokens' => $this->tokens,
      'index'  => $this->index,
    ));
  }

  /**
   * Unserialize the tokenizer.
   *
   * @param string $serialized The serialized tokenizer.
   */
  public function unserialize($serialized) {

    $unserialized = unserialize($serialized);
    $this->__construct($unserialized['tokens']);
    $this->seek($unserialized['index']);
  }

  /**
   * Gets the tokens as an array from the tokenizer.
   *
   * @return array The array of tokens.
   */
  public function asArray() {
    return $this->tokens;
  }

  /**
   * Returns a tokenizer as a string of code.
   *
   * @return string The string of code.
   */
  public function asString() {

    $code = '';

    foreach ($this->tokens as $token) {
      $code .= $token;
    }

    return $code;
  }

  /**
   * Returns a tokenizer as a string of code.
   *
   * @return string The string of code.
   */
  public function __toString() {
    return $this->asString();
  }

} 
