-- Updates from version 0.2-beta

ALTER TABLE cache DROP session_id;

CREATE INDEX session_changed_idx ON session (changed);
CREATE INDEX cache_created_idx ON "cache" (created);

ALTER TABLE users ALTER "language" DROP NOT NULL;
ALTER TABLE users ALTER "language" DROP DEFAULT;

ALTER TABLE identities ALTER del TYPE smallint;
ALTER TABLE identities ALTER standard TYPE smallint;
ALTER TABLE contacts ALTER del TYPE smallint;
ALTER TABLE messages ALTER del TYPE smallint;
