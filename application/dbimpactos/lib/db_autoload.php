<?php

function db_autoload($sClassName, $sDiretorioProjeto = '/var/www/dbportal_prj/') {

  $aIncludeDirs = array();

  $aIncludeDirs[] = "model/";
  $aIncludeDirs[] = "model/pessoal/";
  $aIncludeDirs[] = "model/pessoal/std/";
  $aIncludeDirs[] = "model/pessoal/arquivos/";
  $aIncludeDirs[] = "model/pessoal/arquivos/dirf/";
  $aIncludeDirs[] = "model/pessoal/arquivos/siprev/";
  $aIncludeDirs[] = "model/pessoal/relatorios/";
  $aIncludeDirs[] = "model/compras/";
  $aIncludeDirs[] = "model/orcamento/";
  $aIncludeDirs[] = "model/orcamento/programa/";
  $aIncludeDirs[] = "model/orcamento/suplementacao/";
  $aIncludeDirs[] = "model/arrecadacao/";
  $aIncludeDirs[] = "model/caixa/";
  $aIncludeDirs[] = "model/caixa/arquivos/";
  $aIncludeDirs[] = "model/caixa/slip/";
  $aIncludeDirs[] = "model/educacao/";
  $aIncludeDirs[] = "model/educacao/avaliacao/";
  $aIncludeDirs[] = "model/educacao/censo/";
  $aIncludeDirs[] = "model/educacao/progressaoparcial/";
  $aIncludeDirs[] = "model/educacao/ocorrencia/";
  $aIncludeDirs[] = "model/habitacao/";
  $aIncludeDirs[] = "model/divida/";
  $aIncludeDirs[] = "model/viradaIPTU/";
  $aIncludeDirs[] = "model/cadastro/";
  $aIncludeDirs[] = "model/recursosHumanos/";
  $aIncludeDirs[] = "model/farmacia/";
  $aIncludeDirs[] = "model/juridico/";
  $aIncludeDirs[] = "model/estoque/";
  $aIncludeDirs[] = "model/diversos/";
  $aIncludeDirs[] = "model/contabilidade/";
  $aIncludeDirs[] = "model/contabilidade/contacorrente/";
  $aIncludeDirs[] = "model/contabilidade/arquivos/";
  $aIncludeDirs[] = "model/contabilidade/arquivos/sigfis/";
  $aIncludeDirs[] = "model/contabilidade/relatorios/";
  $aIncludeDirs[] = "model/contabilidade/relatorios/sigfis/";
  $aIncludeDirs[] = "model/contabilidade/lancamento/";
  $aIncludeDirs[] = "model/contabilidade/planoconta/";
  $aIncludeDirs[] = "model/issqn/";
  $aIncludeDirs[] = "model/patrimonio/";
  $aIncludeDirs[] = "model/patrimonio/depreciacao/";
  $aIncludeDirs[] = "model/contrato/";
  $aIncludeDirs[] = "model/ambulatorial/";
  $aIncludeDirs[] = "model/financeiro/";
  $aIncludeDirs[] = "model/empenho/";
  $aIncludeDirs[] = "model/protocolo/";
  $aIncludeDirs[] = "model/configuracao/";
  $aIncludeDirs[] = "model/configuracao/avaliacao/";
  $aIncludeDirs[] = "model/configuracao/notificacao/";
  $aIncludeDirs[] = "model/configuracao/inconsistencia/";
  $aIncludeDirs[] = "model/configuracao/inconsistencia/educacao/";
  $aIncludeDirs[] = "model/social/";
  $aIncludeDirs[] = "model/social/cadastrounico/";
  $aIncludeDirs[] = "model/veiculos/";
  $aIncludeDirs[] = "model/webservices/";

  /**
   * Opcoes alternativas aos diretorios padroes  
   */
  $aExceptions[]  = "std/";
  $aExceptions[]  = "std/exceptions/";

  foreach($aExceptions as $sDiretorioExcecao) {

    $sCaminhoArquivo = $sDiretorioProjeto .$sDiretorioExcecao . $sClassName . '.php'; 

    if ( file_exists($sCaminhoArquivo) ) {
      return $sCaminhoArquivo;
    } 
  }

  if (substr($sClassName, 0, 3) == 'cl_') {

    $sClassNameDao = $sDiretorioProjeto . str_replace("cl_", "db_", $sClassName);
    if ( file_exists($sClassNameDao) ) {
      return "classes/{$sClassNameDao}_classe.php";
    }

  } else {

    foreach($aIncludeDirs as $sDirectory) {

      $sFile = "{$sDiretorioProjeto}{$sDirectory}{$sClassName}.model.php";

      if (file_exists($sFile)) {
        return $sFile;
      }
    }
  }

  return false;
}

