DROP TABLE session;
CREATE TABLE session (
  sess_id varchar(128) NOT NULL PRIMARY KEY,
  changed datetime NOT NULL default '0000-00-00 00:00:00',
  ip varchar(40) NOT NULL default '',
  vars text NOT NULL
);

CREATE INDEX ix_session_changed ON session (changed);
