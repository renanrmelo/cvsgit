
CREATE TABLE IF NOT EXISTS project (
  id      INTEGER PRIMARY KEY AUTOINCREMENT, 
  name    TEXT            NOT NULL, 
  path    TEXT            DEFAULT NULL, 
  date    DATE            DEFAULT NULL
);

CREATE TABLE IF NOT EXISTS pull (
  id         INTEGER PRIMARY KEY AUTOINCREMENT, 
  project_id INTEGER         NOT NULL, 
  title      TEXT            DEFAULT NULL, 
  date       DATE            DEFAULT NULL,
  CONSTRAINT fk_pull_project FOREIGN KEY(project_id) REFERENCES project(id)
);

CREATE INDEX pull_project_in ON pull(project_id);

CREATE TABLE IF NOT EXISTS pull_files (
  id      INTEGER PRIMARY KEY AUTOINCREMENT, 
  pull_id INTEGER         NOT  NULL, 
  name    TEXT            NOT NULL, 
  type    TEXT            DEFAULT NULL, 
  tag     TEXT            DEFAULT NULL, 
  message TEXT            DEFAULT NULL,
  CONSTRAINT fk_pull_files_pull FOREIGN KEY(pull_id) REFERENCES pull(id)
);

CREATE INDEX pull_files_pull_in ON pull_files(pull_id);

/**
 * type : ENH, FIX, ADD, STYLE
 * 
 * command:
 * - 0 : commit e tag
 * - 1 : adicionar tag
 * - 2 : mover tag
 * - 3 : deletar tag
 * - 4 : deletar arquivo e tag
 */
CREATE TABLE IF NOT EXISTS add_files (
  id           INTEGER PRIMARY KEY AUTOINCREMENT, 
  project_id   INTEGER         NOT NULL, 
  file         TEXT            NOT NULL, 
  tag_message  TEXT            DEFAULT NULL,
  tag_file     TEXT            DEFAULT NULL,
  message      TEXT            DEFAULT NULL,
  type         TEXT            DEFAULT NULL,
  command      INTEGER         DEFAULT 0
);

CREATE TABLE IF NOT EXISTS tag_files (
  id           INTEGER PRIMARY KEY AUTOINCREMENT, 
  project_id   INTEGER         NOT NULL, 
  file         TEXT            NOT NULL, 
  tag          TEXT            DEFAULT NULL
);

CREATE TABLE IF NOT EXISTS history (
  id          INTEGER PRIMARY KEY AUTOINCREMENT, 
  project_id  INTEGER NOT NULL, 
  date        DATE    DEFAULT NULL
);

CREATE TABLE IF NOT EXISTS history_file (
  id           INTEGER PRIMARY KEY AUTOINCREMENT, 
  project_id   INTEGER         NOT NULL, 
  name         TEXT            NOT NULL, 
  revision     TEXT            DEFAULT NULL, 
  message      TEXT            DEFAULT NULL,
  author       TEXT            DEFAULT NULL,
  date         DATE            DEFAULT NULL,
  CONSTRAINT fk_history_file_project FOREIGN KEY(project_id) REFERENCES project(id)
);

CREATE INDEX history_file_project_in ON history_file(project_id);

CREATE TABLE IF NOT EXISTS history_file_tag (
  id               INTEGER PRIMARY KEY AUTOINCREMENT, 
  history_file_id  INTEGER         NOT NULL, 
  tag              TEXT            DEFAULT NULL,
  CONSTRAINT fk_history_file_tag_history_file FOREIGN KEY(history_file_id) REFERENCES history_file(id)
);

CREATE INDEX history_file_tag_history_file_in ON history_file_tag(history_file_id);
