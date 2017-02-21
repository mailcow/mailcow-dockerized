DROP TABLE cache;
DROP TABLE cache_shared;

CREATE TABLE cache (
  user_id integer NOT NULL default 0,
  cache_key varchar(128) NOT NULL default '',
  expires datetime DEFAULT NULL,
  data text NOT NULL,
  PRIMARY KEY (user_id, cache_key)
);

CREATE INDEX ix_cache_expires ON cache(expires);

CREATE TABLE cache_shared (
  cache_key varchar(255) NOT NULL,
  expires datetime DEFAULT NULL,
  data text NOT NULL,
  PRIMARY KEY (cache_key)
);

CREATE INDEX ix_cache_shared_expires ON cache_shared(expires);
