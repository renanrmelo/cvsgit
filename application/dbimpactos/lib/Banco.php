<?php

/**
 * Banco de dados usando arquivo sqlite
 * 
 * @uses PDO
 * @package Library 
 * @version 1.0
 */
class Banco extends PDO {
	
	private static $oInstancia;
	
  public function __construct($sArquivoBanco) {

    parent::__construct('sqlite:' . $sArquivoBanco);
    $this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    self::$oInstancia = $this;
  }
  
  public static function getInstancia() {
  	return self::$oInstancia;
  }  

  /**
   * Inicia uma transacao 
   * 
   * @access public
   * @return bool
   */
  public function begin() {
    return $this->beginTransaction();
  }
  
  /**
   * Grava as modificaoes 
   * 
   * @access public
   * @return bool
   */
  public function commit() {

    if ( !$this->inTransaction() ) {
      return false;
    }
    
    return parent::commit();
  }
  
  /**
   * Desfaz alteracoes feitas 
   * 
   * @access public
   * @return bool
   */
  public function rollBack() {

    if ( !$this->inTransaction() ) {
      return false;
    }

    return parent::rollBack();
  }

  /**
   * Roda comando vaccum na base
   *
   * @access public
   * @return bool
   */
  public function vacuum() {
    return $this->query("VACUUM");
  }

  /**
   * Executa um arquivo no banco
   *
   * @param string $sArquivoSql
   * @access public
   * @return void
   */
  public function executeFile($sArquivoSql) {

    $sSqlDump    = file_get_contents($sArquivoSql);
    $this->exec($sSqlDump);
    $this->vacuum();
  }

	public function insert($tabela, Array $aDados) {		
		
		$iLinha   = 0;
		$sCampos  = '';
		$sValores = '';
		$aBindValues = array();
		
		foreach ( $aDados as $sCampo => $sValor ) {
			
			if ( $iLinha > 0 ) {
								
				$sValores .= ',';
				$sCampos .= ',';
			}
			
			$sCampos  .= $sCampo;
			$sValores .= ':' . $sCampo;
			$aBindValues[':' . $sCampo] = $sValor;
			$iLinha++;
		}				

		$insert = $this->prepare(" INSERT INTO `{$tabela}` ({$sCampos}) VALUES ({$sValores}) ");
		$insert->execute($aBindValues);

    /**
     * ultimo id
     */
    return $this->lastInsertId(); 
	}

	public function update($tabela, Array $dados, $where = null) {

		$where = !empty($where) ? 'WHERE '.$where : null;

		foreach ( $dados as $ind => $val ) {
			$campos[] = "{$ind} = '{$val}'";
		}

		$campos = implode(", ", $campos);
		return $this->query(" UPDATE `{$tabela}` SET {$campos} {$where} ");
	}

	public function delete($tabela, $where = null) {

		$where = !empty($where) ? 'WHERE ' . $where : null;
		return $this->exec(" DELETE FROM `{$tabela}` {$where} ");
	}

  public function select($sSql) {

    try { 

      $oQuery = $this->query("$sSql");
      $oQuery->setFetchMode(PDO::FETCH_OBJ);

      return $oQuery->fetch();

    } catch ( Exception $erro ) {
      throw new Exception($erro->getMessage());
    }
  }

  public function selectAll($sSql) {

    try { 

      $oQuery = $this->query("$sSql");
      $oQuery->setFetchMode(PDO::FETCH_OBJ);
      $aResult = $oQuery->fetchAll();

      if ( !$aResult ) {
        $aResult = array();
      }

      return $aResult;

    } catch ( Exception $erro ) {
      throw new Exception($erro->getMessage());
    }

  }

}
