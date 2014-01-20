<?php

function conectarDicionarioDados() {

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

function getMenus() {

  try {
    $conexao = conectarDicionarioDados();

    $rsMenus = pg_query($conexao, "
      select id_item as id, funcao as programa, fc_montamenu(id_item) as caminho 
        from db_itensmenu
       where trim(funcao) != '' and trim(funcao) is not null 
         and fc_montamenu(id_item) is not null
    ");

    $aMenus = array();
    
    while ( $oMenu = pg_fetch_object($rsMenus) ) {

      $oMenu->caminho = toUTF8($oMenu->caminho);
      $aMenus[] = $oMenu;
    }

    file_put_contents(PATH . 'menu.cache', serialize($aMenus));
    return $aMenus;

  } catch (Exception $oErro) {

    $oOutput = new \Symfony\Component\Console\Output\ConsoleOutput();
    $oOutput->writeln('<error>' . $oErro->getMessage() . '</error>');
    return unserialize(file_get_contents(PATH . 'menu.cache'));
  }

}

function toUTF8($sText) {
  return mb_convert_encoding($sText, "UTF-8", mb_detect_encoding($sText, "UTF-8, ISO-8859-1, ISO-8859-15", true));
}

function db_autoload($sClassName, $sDiretorioProjeto = '/var/www/dbportal_prj/') {

  if (substr($sClassName, 0, 3) == 'cl_') {

    $sClassNameDao = $sDiretorioProjeto . 'classes/' . str_replace("cl_", "db_", $sClassName) . '_classe.php';

    if ( file_exists($sClassNameDao) ) {
      return $sClassNameDao;
    }

    return false;
  } 

  $aDiretorios = array('model', 'libs', 'std');
  $aIgnorar = array('.', '..');

  foreach ($aDiretorios as $sDiretorio) {

    $oDiretorio = new RecursiveDirectoryIterator($sDiretorioProjeto . $sDiretorio);
    $oDiretorioIterator = new RecursiveIteratorIterator($oDiretorio, RecursiveIteratorIterator::SELF_FIRST);

    foreach($oDiretorioIterator as $sCaminhoArquivo => $oArquivo) {

      if ( $oArquivo->isDir() ) {
        continue;
      }

      if ( !in_array($oArquivo->getExtension(), array('php')) ) {
        continue;
      }

      if ( in_array($oArquivo->getFileName(), $aIgnorar) ) {
        continue;
      }

      if (strtolower($oArquivo->getFileName()) == strtolower($sClassName) . '.model.php') {
        return $sCaminhoArquivo;
      }

      if (strtolower($oArquivo->getFileName()) == strtolower($sClassName) . '.php') {
        return $sCaminhoArquivo;
      } 

    } 

  }

  return false;
}
