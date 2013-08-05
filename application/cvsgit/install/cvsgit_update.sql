
ALTER TABLE pull             RENAME TO pull_bkp;
ALTER TABLE pull_files       RENAME TO pull_files_bkp;
ALTER TABLE history_file     RENAME TO history_file_bkp;
ALTER TABLE history_file_tag RENAME TO history_file_tag_bkp;

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

INSERT INTO pull             SELECT * FROM pull_bkp;
INSERT INTO pull_files       SELECT * FROM pull_files_bkp;
INSERT INTO history_file     SELECT * FROM history_file_bkp;
INSERT INTO history_file_tag SELECT * FROM history_file_tag_bkp;

DROP TABLE pull_bkp;
DROP TABLE pull_files_bkp;
DROP TABLE history_file_bkp;
DROP TABLE history_file_tag_bkp;
