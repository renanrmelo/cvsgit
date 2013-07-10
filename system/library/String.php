<?php

class String {

  /**
   * Remover os acentos de uma string
   *
   * @param string $sString
   * @static
   * @access public
   * @return string
   */
  public static function removeAccents($sString){

    $sFrom   = 'ÀÁÃÂÉÊÍÓÕÔÚÜÇàáãâéêíóõôúüç';
    $sTo     = 'AAAAEEIOOOUUCaaaaeeiooouuc';
    $sString = strtr($sString, $sFrom, $sTo);

    return $sString;
  }

}
