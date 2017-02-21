-- Updates from version 0.3-stable

DELETE FROM messages;
DROP INDEX ix_messages_user_cache_uid;
CREATE UNIQUE INDEX ix_messages_user_cache_uid ON messages (user_id,cache_key,uid);
CREATE INDEX ix_messages_index ON messages (user_id,cache_key,idx);
DROP INDEX ix_contacts_user_id;
CREATE INDEX ix_contacts_user_id ON contacts(user_id, email);
