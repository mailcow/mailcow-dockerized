-- Updates from version 0.7

DROP INDEX contacts_user_id_idx;
CREATE INDEX contacts_user_id_idx ON contacts USING btree (user_id, del);
ALTER TABLE contacts ALTER email TYPE text;
