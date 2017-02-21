-- Updates from version 0.8

DROP TABLE cache;
CREATE TABLE cache (
  user_id integer NOT NULL default 0,
  cache_key varchar(128) NOT NULL default '',
  created datetime NOT NULL default '0000-00-00 00:00:00',
  data text NOT NULL
);

CREATE INDEX ix_cache_user_cache_key ON cache(user_id, cache_key);
CREATE INDEX ix_cache_created ON cache(created);

CREATE TABLE tmp_users (
  user_id integer NOT NULL PRIMARY KEY,
  username varchar(128) NOT NULL default '',
  mail_host varchar(128) NOT NULL default '',
  created datetime NOT NULL default '0000-00-00 00:00:00',
  last_login datetime DEFAULT NULL,
  language varchar(5),
  preferences text NOT NULL default ''
);

INSERT INTO tmp_users (user_id, username, mail_host, created, last_login, language, preferences)
    SELECT user_id, username, mail_host, created, last_login, language, preferences FROM users;

DROP TABLE users;

CREATE TABLE users (
  user_id integer NOT NULL PRIMARY KEY,
  username varchar(128) NOT NULL default '',
  mail_host varchar(128) NOT NULL default '',
  created datetime NOT NULL default '0000-00-00 00:00:00',
  last_login datetime DEFAULT NULL,
  language varchar(5),
  preferences text NOT NULL default ''
);

INSERT INTO users (user_id, username, mail_host, created, last_login, language, preferences)
    SELECT user_id, username, mail_host, created, last_login, language, preferences FROM tmp_users;

CREATE UNIQUE INDEX ix_users_username ON users(username, mail_host);

CREATE INDEX ix_identities_email ON identities(email, del);
