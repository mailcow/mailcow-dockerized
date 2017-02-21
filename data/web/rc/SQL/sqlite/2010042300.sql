-- Updates from version 0.4-beta

CREATE TABLE tmp_users (
  user_id integer NOT NULL PRIMARY KEY,
  username varchar(128) NOT NULL default '',
  mail_host varchar(128) NOT NULL default '',
  alias varchar(128) NOT NULL default '',
  created datetime NOT NULL default '0000-00-00 00:00:00',
  last_login datetime NOT NULL default '0000-00-00 00:00:00',
  language varchar(5),
  preferences text NOT NULL default ''
);

INSERT INTO tmp_users (user_id, username, mail_host, alias, created, last_login, language, preferences)
    SELECT user_id, username, mail_host, alias, created, last_login, language, preferences FROM users;

DROP TABLE users;

CREATE TABLE users (
  user_id integer NOT NULL PRIMARY KEY,
  username varchar(128) NOT NULL default '',
  mail_host varchar(128) NOT NULL default '',
  alias varchar(128) NOT NULL default '',
  created datetime NOT NULL default '0000-00-00 00:00:00',
  last_login datetime DEFAULT NULL,
  language varchar(5),
  preferences text NOT NULL default ''
);

INSERT INTO users (user_id, username, mail_host, alias, created, last_login, language, preferences)
    SELECT user_id, username, mail_host, alias, created, last_login, language, preferences FROM tmp_users;

CREATE INDEX ix_users_username ON users(username);
CREATE INDEX ix_users_alias ON users(alias);
DROP TABLE tmp_users;
