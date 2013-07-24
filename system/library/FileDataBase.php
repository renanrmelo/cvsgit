<?php

/**
 * Banco de dados usando arquivo sqlite
 * 
 * @uses PDO
 * @package Library 
 * @version $id$
 */
class FileDataBase extends PDO {

  public function __construct($sArquivoBanco) {

    /**
     * Arquivo do banco nao existe 
     */
    if ( !file_exists($sArquivoBanco) ) {
      throw new \Exception("Arquivo não existe: $sArquivoBanco");
    }

    parent::__construct('sqlite:' . $sArquivoBanco);
    $this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $this;
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

	public function insert($tabela, Array $dados) {
		
		$campos = implode(", ", array_keys($dados));
		$valores = "'".implode("','", array_values($dados))."'";

		$insert = $this->exec(" INSERT INTO `{$tabela}` ({$campos}) VALUES ({$valores}) ");

    /**
     * ultimo id
     */
    return $this->lastInsertId(); 
	}

	public function update($tabela, Array $dados, $where) {

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

		$oQuery = $this->query("$sSql");
		$oQuery->setFetchMode(PDO::FETCH_OBJ);

		return $oQuery->fetch();
  }

  public function selectAll($sSql) {

		$oQuery = $this->query("$sSql");
		$oQuery->setFetchMode(PDO::FETCH_OBJ);
		$aResult = $oQuery->fetchAll();

    if ( !$aResult ) {
      $aResult = array();
    }

    return $aResult;
  }

}
