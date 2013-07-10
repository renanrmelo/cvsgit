<?php
/**
 * Configuracoes
 */
return array(

  /**
   * Diretorio 
   * - Diretorio usado pelo programa, sera criado
   * - Objetos de commit, configuracoes e arquivos temporarios
   */
  'sDiretorioObjetos' => getenv('HOME'),

  /**
   * Acentos
   * - Remover acentos das mensagens de commit 
   */
  'lRemoverAcentos' => false,

  /**
   * Senha do root, usado no no comando cvsgit pull 
   */
  'sRootPassword' => '',

);
