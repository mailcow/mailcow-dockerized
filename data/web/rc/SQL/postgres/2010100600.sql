-- Updates from version 0.4.2

DROP INDEX users_username_id_idx;
ALTER TABLE users ADD CONSTRAINT users_username_key UNIQUE (username, mail_host);
ALTER TABLE contacts ALTER email TYPE varchar(255);

TRUNCATE messages;
