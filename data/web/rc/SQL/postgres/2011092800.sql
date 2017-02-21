-- Updates from version 0.6

CREATE TABLE dictionary (
    user_id integer DEFAULT NULL
        REFERENCES users (user_id) ON DELETE CASCADE ON UPDATE CASCADE,
   "language" varchar(5) NOT NULL,
    data text NOT NULL,
    CONSTRAINT dictionary_user_id_language_key UNIQUE (user_id, "language")
);

CREATE SEQUENCE search_ids
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;

CREATE TABLE searches (
    search_id integer DEFAULT nextval('search_ids'::text) PRIMARY KEY,
    user_id integer NOT NULL
        REFERENCES users (user_id) ON DELETE CASCADE ON UPDATE CASCADE,
    "type" smallint DEFAULT 0 NOT NULL,
    name varchar(128) NOT NULL,
    data text NOT NULL,
    CONSTRAINT searches_user_id_key UNIQUE (user_id, "type", name)
);

DROP SEQUENCE message_ids;
DROP TABLE messages;

CREATE TABLE cache_index (
    user_id integer NOT NULL
        REFERENCES users (user_id) ON DELETE CASCADE ON UPDATE CASCADE,
    mailbox varchar(255) NOT NULL,
    changed timestamp with time zone DEFAULT now() NOT NULL,
    valid smallint NOT NULL DEFAULT 0,
    data text NOT NULL,
    PRIMARY KEY (user_id, mailbox)
);

CREATE INDEX cache_index_changed_idx ON cache_index (changed);

CREATE TABLE cache_thread (
    user_id integer NOT NULL
        REFERENCES users (user_id) ON DELETE CASCADE ON UPDATE CASCADE,
    mailbox varchar(255) NOT NULL,
    changed timestamp with time zone DEFAULT now() NOT NULL,
    data text NOT NULL,
    PRIMARY KEY (user_id, mailbox)
);

CREATE INDEX cache_thread_changed_idx ON cache_thread (changed);

CREATE TABLE cache_messages (
    user_id integer NOT NULL
        REFERENCES users (user_id) ON DELETE CASCADE ON UPDATE CASCADE,
    mailbox varchar(255) NOT NULL,
    uid integer NOT NULL,
    changed timestamp with time zone DEFAULT now() NOT NULL,
    data text NOT NULL,
    flags integer NOT NULL DEFAULT 0,
    PRIMARY KEY (user_id, mailbox, uid)
);

CREATE INDEX cache_messages_changed_idx ON cache_messages (changed);
