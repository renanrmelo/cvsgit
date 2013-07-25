
/**
 * Projetos 
 */
CREATE TABLE IF NOT EXISTS project (
  id      INTEGER PRIMARY KEY AUTOINCREMENT, 
  name    TEXT            NOT NULL, 
  path    TEXT            DEFAULT NULL, 
  date    DATE            DEFAULT NULL
);

/**
 * Pull(commits enviados)
 * title -titulo do commit
 * date - data do comit
 */
CREATE TABLE IF NOT EXISTS pull (
  id         INTEGER PRIMARY KEY AUTOINCREMENT, 
  project_id INTEGER         NOT NULL, 
  title      TEXT            DEFAULT NULL, 
  date       DATE            DEFAULT NULL
);

/**
 * Detalhes do pull(commit enviados)
 * arquivos
 * tag
 * mensagem do commit
 */
CREATE TABLE IF NOT EXISTS pull_files (
  id      INTEGER PRIMARY KEY AUTOINCREMENT, 
  pull_id INTEGER         NOT  NULL, 
  name    TEXT            NOT NULL, 
  type    TEXT            DEFAULT NULL, 
  tag     TEXT            DEFAULT NULL, 
  message TEXT            DEFAULT NULL
);

CREATE TABLE IF NOT EXISTS add_files (
  id           INTEGER PRIMARY KEY AUTOINCREMENT, 
  project_id   INTEGER         NOT NULL, 
  file         TEXT            NOT NULL, 
  tag_message  TEXT            DEFAULT NULL,
  tag_file     TEXT            DEFAULT NULL,
  message      TEXT            DEFAULT NULL,
  type_short   TEXT            DEFAULT NULL,
  type_full    TEXT            DEFAULT NULL
);

CREATE TABLE IF NOT EXISTS history (
  id    INTEGER PRIMARY KEY AUTOINCREMENT, 
  date  DATE            DEFAULT NULL
);

CREATE TABLE IF NOT EXISTS history_file (
  id           INTEGER PRIMARY KEY AUTOINCREMENT, 
  project_id   INTEGER         NOT NULL, 
  name         TEXT            NOT NULL, 
  revision     TEXT            DEFAULT NULL, 
  message      TEXT            DEFAULT NULL,
  author       TEXT            DEFAULT NULL,
  date         DATE            DEFAULT NULL
);

CREATE TABLE IF NOT EXISTS history_file_tag (
  id               INTEGER PRIMARY KEY AUTOINCREMENT, 
  history_file_id  INTEGER         NOT NULL, 
  tag              TEXT            DEFAULT NULL
);
