-- Updates from version 0.8

ALTER TABLE cache DROP COLUMN cache_id;
DROP SEQUENCE cache_ids;

ALTER TABLE users DROP COLUMN alias;
CREATE INDEX identities_email_idx ON identities (email, del);
