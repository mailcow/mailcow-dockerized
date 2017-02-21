-- Updates from version 0.1-stable to 0.1.1

CREATE INDEX cache_user_id_idx ON cache (user_id, cache_key);
CREATE INDEX contacts_user_id_idx ON contacts (user_id);
CREATE INDEX identities_user_id_idx ON identities (user_id);

CREATE INDEX users_username_id_idx ON users (username);
CREATE INDEX users_alias_id_idx ON users (alias);

-- added ON DELETE/UPDATE actions
ALTER TABLE messages DROP CONSTRAINT messages_user_id_fkey;
ALTER TABLE messages ADD FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE identities DROP CONSTRAINT identities_user_id_fkey;
ALTER TABLE identities ADD FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE contacts DROP CONSTRAINT contacts_user_id_fkey;
ALTER TABLE contacts ADD FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE cache DROP CONSTRAINT cache_user_id_fkey;
ALTER TABLE cache ADD FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE ON UPDATE CASCADE;
