CREATE TABLE "cache_shared" (
    cache_key varchar(255) NOT NULL,
    created timestamp with time zone DEFAULT now() NOT NULL,
    data text NOT NULL
);

CREATE INDEX cache_shared_cache_key_idx ON "cache_shared" (cache_key);
CREATE INDEX cache_shared_created_idx ON "cache_shared" (created);
