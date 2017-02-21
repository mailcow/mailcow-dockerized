-- Updates from version 0.6

CREATE TABLE dictionary (
    user_id integer DEFAULT NULL,
   "language" varchar(5) NOT NULL,
    data text NOT NULL
);

CREATE UNIQUE INDEX ix_dictionary_user_language ON dictionary (user_id, "language");

CREATE TABLE searches (
  search_id integer NOT NULL PRIMARY KEY,
  user_id integer NOT NULL DEFAULT '0',
  "type" smallint NOT NULL DEFAULT '0',
  name varchar(128) NOT NULL,
  data text NOT NULL
);

CREATE UNIQUE INDEX ix_searches_user_type_name ON searches (user_id, type, name);

DROP TABLE messages;

CREATE TABLE cache_index (
    user_id integer NOT NULL,
    mailbox varchar(255) NOT NULL,
    changed datetime NOT NULL default '0000-00-00 00:00:00',
    valid smallint NOT NULL DEFAULT '0',
    data text NOT NULL,
    PRIMARY KEY (user_id, mailbox)
);

CREATE INDEX ix_cache_index_changed ON cache_index (changed);

CREATE TABLE cache_thread (
    user_id integer NOT NULL,
    mailbox varchar(255) NOT NULL,
    changed datetime NOT NULL default '0000-00-00 00:00:00',
    data text NOT NULL,
    PRIMARY KEY (user_id, mailbox)
);

CREATE INDEX ix_cache_thread_changed ON cache_thread (changed);

CREATE TABLE cache_messages (
    user_id integer NOT NULL,
    mailbox varchar(255) NOT NULL,
    uid integer NOT NULL,
    changed datetime NOT NULL default '0000-00-00 00:00:00',
    data text NOT NULL,
    flags integer NOT NULL DEFAULT '0',
    PRIMARY KEY (user_id, mailbox, uid)
);

CREATE INDEX ix_cache_messages_changed ON cache_messages (changed);
