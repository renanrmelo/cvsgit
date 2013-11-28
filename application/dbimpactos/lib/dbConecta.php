<?php

function dbconecta() {

	$DB_SERVIDOR = "mentor.dbseller";
	$DB_BASE     = "ecidade_dicionario_dados";
	$DB_PORTA    = "5432";
	$DB_USUARIO  = "ecidade";
	$DB_SENHA    = "";

	$conexao = @pg_connect("host=$DB_SERVIDOR  dbname=$DB_BASE  port=$DB_PORTA user=$DB_USUARIO password=$DB_SENHA");
			
	if ( !$conexao ) {
		throw new Exception('Erro ao conectar ao banco: ' . $DB_SERVIDOR);
	}

	pg_query('select fc_startsession()');
	return $conexao;
}

/**
 * Retorna o Caminho do Menu passado por parametro()
 * @Param Mixed $mMenu Codigo , ou Nome do Arquivo
 */
function getCaminhoMenu($mMenu) {

	$sBusca = "'{$mMenu}'";
	if (is_int($mMenu)) {
		$sBusca = "{$mMenu}";
	}
	$rsCaminhoMenu = pg_query("select fc_montamenu({$sBusca}) as caminho");
	$sCaminhoMenu = pg_fetch_object($rsCaminhoMenu, 0)->caminho;

	return toUTF8($sCaminhoMenu);
}

function toUTF8($sText) {
  return mb_convert_encoding($sText, "UTF-8", mb_detect_encoding($sText, "UTF-8, ISO-8859-1, ISO-8859-15", true));
}

