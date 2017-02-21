DROP TABLE cache_index;
DROP TABLE cache_thread;
DROP TABLE cache_messages;

ALTER TABLE cache ADD expires datetime DEFAULT NULL;
DROP INDEX ix_cache_created;

ALTER TABLE cache_shared ADD expires datetime DEFAULT NULL;
DROP INDEX ix_cache_shared_created;

UPDATE cache SET expires = datetime(created, '+604800 seconds');
UPDATE cache_shared SET expires = datetime(created, '+604800 seconds');

CREATE INDEX ix_cache_expires ON cache(expires);
CREATE INDEX ix_cache_shared_expires ON cache_shared(expires);

CREATE TABLE cache_index (
    user_id integer NOT NULL,
    mailbox varchar(255) NOT NULL,
    expires datetime DEFAULT NULL,
    valid smallint NOT NULL DEFAULT '0',
    data text NOT NULL,
    PRIMARY KEY (user_id, mailbox)
);

CREATE INDEX ix_cache_index_expires ON cache_index (expires);

CREATE TABLE cache_thread (
    user_id integer NOT NULL,
    mailbox varchar(255) NOT NULL,
    expires datetime DEFAULT NULL,
    data text NOT NULL,
    PRIMARY KEY (user_id, mailbox)
);

CREATE INDEX ix_cache_thread_expires ON cache_thread (expires);

CREATE TABLE cache_messages (
    user_id integer NOT NULL,
    mailbox varchar(255) NOT NULL,
    uid integer NOT NULL,
    expires datetime DEFAULT NULL,
    data text NOT NULL,
    flags integer NOT NULL DEFAULT '0',
    PRIMARY KEY (user_id, mailbox, uid)
);

CREATE INDEX ix_cache_messages_expires ON cache_messages (expires);
