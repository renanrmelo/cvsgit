
/**
 * Arquivo
 */
CREATE TABLE IF NOT EXISTS arquivo (
  id          INTEGER PRIMARY KEY AUTOINCREMENT, 
  caminho     TEXT            DEFAULT NULL, 
  modificado  DATE            DEFAULT NULL,
  tipo        INTEGER         DEFAULT NULL,
  linhas      INTEGER         DEFAULT NULL
);

/**
 * Classe
 */
CREATE TABLE IF NOT EXISTS classe (
  id          INTEGER PRIMARY KEY AUTOINCREMENT, 
  arquivo     INTEGER         DEFAULT NULL, 
  nome        TEXT            DEFAULT NULL,
  CONSTRAINT fk_classe_arquivo FOREIGN KEY(arquivo) REFERENCES arquivo(id)
);
CREATE INDEX classe_arquivo_in ON classe(arquivo);

/**
 * Metodo
 */
CREATE TABLE IF NOT EXISTS metodo (
  id          INTEGER PRIMARY KEY AUTOINCREMENT, 
  classe      INTEGER         DEFAULT NULL, 
  nome        TEXT            DEFAULT NULL,
  CONSTRAINT fk_metodo_classe FOREIGN KEY(classe) REFERENCES classe(id)
);
CREATE INDEX metodo_classe_in ON metodo(classe);

/**
 * Constants da classe, usando const
 */
CREATE TABLE IF NOT EXISTS classe_constant (
  id          INTEGER PRIMARY KEY AUTOINCREMENT, 
  classe      INTEGER         DEFAULT NULL, 
  nome        TEXT            DEFAULT NULL,
  CONSTRAINT fk_classe_constant_classe FOREIGN KEY(classe) REFERENCES classe(id)
);
CREATE INDEX classe_constant_classe_in ON classe_constant(classe);

/**
 * Constants do arquivo, usando define()
 */
CREATE TABLE IF NOT EXISTS constant (
  id          INTEGER PRIMARY KEY AUTOINCREMENT, 
  arquivo     INTEGER         DEFAULT NULL, 
  nome        TEXT            DEFAULT NULL,
  CONSTRAINT fk_constant_arquivo FOREIGN KEY(arquivo) REFERENCES arquivo(id)
);
CREATE INDEX constant_arquivo_in ON constant(arquivo);

/**
 * Funcao
 */
CREATE TABLE IF NOT EXISTS funcao (
  id          INTEGER PRIMARY KEY AUTOINCREMENT, 
  arquivo     INTEGER         NOT NULL, 
  nome        TEXT            DEFAULT NULL, 
  CONSTRAINT fk_funcao_arquivo FOREIGN KEY(arquivo) REFERENCES arquivo(id)
);
CREATE INDEX funcao_arquivo_in ON funcao(arquivo);

/**
 * Require
 */
CREATE TABLE IF NOT EXISTS require (
  id                INTEGER PRIMARY KEY AUTOINCREMENT, 
  arquivo           INTEGER         DEFAULT NULL, 
  arquivo_require   INTEGER         DEFAULT NULL, 
  linha             INTEGER         DEFAULT NULL, 
  utiliza           BOOLEAN         DEFAULT FALSE,
  CONSTRAINT fk_require_arquivo         FOREIGN KEY(arquivo)         REFERENCES arquivo(id)
  CONSTRAINT fk_require_arquivo_require FOREIGN KEY(arquivo_require) REFERENCES arquivo(id)
);
CREATE INDEX require_arquivo_in         ON require(arquivo);
CREATE INDEX require_arquivo_require_in ON require(arquivo_require);

/**
 * Menu
 */
CREATE TABLE IF NOT EXISTS menu (
  id          INTEGER PRIMARY KEY AUTOINCREMENT, 
  caminho     TEXT            DEFAULT NULL,
  programa    TEXT            DEFAULT NULL
);

/**
 * Menu arquivo
 */
CREATE TABLE IF NOT EXISTS menu_arquivo (
  id          INTEGER PRIMARY KEY AUTOINCREMENT, 
  arquivo     INTEGER         NOT NULL, 
  menu        INTEGER         NOT NULL, 
  CONSTRAINT fk_menu_arquivo_arquivo FOREIGN KEY(arquivo) REFERENCES arquivo(id),
  CONSTRAINT fk_menu_arquivo_menu    FOREIGN KEY(menu)    REFERENCES menu(id)
);
CREATE INDEX menu_arquivo_arquivo_in ON menu_arquivo(arquivo);
CREATE INDEX menu_arquivo_menu_in    ON menu_arquivo(menu);

/**
 * Log
 */
CREATE TABLE IF NOT EXISTS log (
  id          INTEGER PRIMARY KEY AUTOINCREMENT, 
  arquivo     INTEGER         NOT NULL, 
  log         TEXT            DEFAULT NULL,
  CONSTRAINT fk_log_arquivo FOREIGN KEY(arquivo) REFERENCES arquivo(id)
);
CREATE INDEX log_arquivo ON log(arquivo);
