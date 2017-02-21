CREATE TABLE cache_shared (
  cache_key varchar(255) NOT NULL,
  created datetime NOT NULL default '0000-00-00 00:00:00',
  data text NOT NULL
);

CREATE INDEX ix_cache_shared_cache_key ON cache_shared(cache_key);
CREATE INDEX ix_cache_shared_created ON cache_shared(created);
