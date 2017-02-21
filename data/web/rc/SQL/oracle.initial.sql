-- Roundcube Webmail initial database structure
-- This was tested with Oracle 11g

CREATE TABLE "users" (
    "user_id" integer PRIMARY KEY,
    "username" varchar(128) NOT NULL,
    "mail_host" varchar(128) NOT NULL,
    "created" timestamp with time zone DEFAULT current_timestamp NOT NULL,
    "last_login" timestamp with time zone DEFAULT NULL,
    "failed_login" timestamp with time zone DEFAULT NULL,
    "failed_login_counter" integer DEFAULT NULL,
    "language" varchar(5),
    "preferences" long DEFAULT NULL,
    CONSTRAINT "users_username_key" UNIQUE ("username", "mail_host")
);

CREATE SEQUENCE "users_seq"
    START WITH 1 INCREMENT BY 1 NOMAXVALUE;

CREATE TRIGGER "users_seq_trig"
BEFORE INSERT ON "users" FOR EACH ROW
BEGIN
    :NEW."user_id" := "users_seq".nextval;
END;
/

CREATE TABLE "session" (
    "sess_id" varchar(128) NOT NULL PRIMARY KEY,
    "changed" timestamp with time zone DEFAULT current_timestamp NOT NULL,
    "ip" varchar(41) NOT NULL,
    "vars" long NOT NULL
);

CREATE INDEX "session_changed_idx" ON "session" ("changed");


CREATE TABLE "identities" (
    "identity_id" integer PRIMARY KEY,
    "user_id" integer NOT NULL
        REFERENCES "users" ("user_id") ON DELETE CASCADE,
    "changed" timestamp with time zone DEFAULT current_timestamp NOT NULL,
    "del" smallint DEFAULT 0 NOT NULL,
    "standard" smallint DEFAULT 0 NOT NULL,
    "name" varchar(128) NOT NULL,
    "organization" varchar(128),
    "email" varchar(128) NOT NULL,
    "reply-to" varchar(128),
    "bcc" varchar(128),
    "signature" long,
    "html_signature" integer DEFAULT 0 NOT NULL
);

CREATE INDEX "identities_user_id_idx" ON "identities" ("user_id", "del");
CREATE INDEX "identities_email_idx" ON "identities" ("email", "del");

CREATE SEQUENCE "identities_seq"
    START WITH 1 INCREMENT BY 1 NOMAXVALUE;

CREATE TRIGGER "identities_seq_trig"
BEFORE INSERT ON "identities" FOR EACH ROW
BEGIN
    :NEW."identity_id" := "identities_seq".nextval;
END;
/

CREATE TABLE "contacts" (
    "contact_id" integer PRIMARY KEY,
    "user_id" integer NOT NULL
        REFERENCES "users" ("user_id") ON DELETE CASCADE,
    "changed" timestamp with time zone DEFAULT current_timestamp NOT NULL,
    "del" smallint DEFAULT 0 NOT NULL,
    "name" varchar(128) DEFAULT NULL,
    "email" varchar(4000) DEFAULT NULL,
    "firstname" varchar(128) DEFAULT NULL,
    "surname" varchar(128) DEFAULT NULL,
    "vcard" long,
    "words" varchar(4000)
);

CREATE INDEX "contacts_user_id_idx" ON "contacts" ("user_id", "del");

CREATE SEQUENCE "contacts_seq"
    START WITH 1 INCREMENT BY 1 NOMAXVALUE;

CREATE TRIGGER "contacts_seq_trig"
BEFORE INSERT ON "contacts" FOR EACH ROW
BEGIN
    :NEW."contact_id" := "contacts_seq".nextval;
END;
/

CREATE TABLE "contactgroups" (
    "contactgroup_id" integer PRIMARY KEY,
    "user_id" integer NOT NULL
        REFERENCES "users" ("user_id") ON DELETE CASCADE,
    "changed" timestamp with time zone DEFAULT current_timestamp NOT NULL,
    "del" smallint DEFAULT 0 NOT NULL,
    "name" varchar(128) NOT NULL
);

CREATE INDEX "contactgroups_user_id_idx" ON "contactgroups" ("user_id", "del");

CREATE SEQUENCE "contactgroups_seq"
    START WITH 1 INCREMENT BY 1 NOMAXVALUE;

CREATE TRIGGER "contactgroups_seq_trig"
BEFORE INSERT ON "contactgroups" FOR EACH ROW
BEGIN
    :NEW."contactgroup_id" := "contactgroups_seq".nextval;
END;
/

CREATE TABLE "contactgroupmembers" (
    "contactgroup_id" integer NOT NULL
        REFERENCES "contactgroups" ("contactgroup_id") ON DELETE CASCADE,
    "contact_id" integer NOT NULL
        REFERENCES "contacts" ("contact_id") ON DELETE CASCADE,
    "created" timestamp with time zone DEFAULT current_timestamp NOT NULL,
    PRIMARY KEY ("contactgroup_id", "contact_id")
);

CREATE INDEX "contactgroupmembers_idx" ON "contactgroupmembers" ("contact_id");


CREATE TABLE "cache" (
    "user_id" integer NOT NULL
        REFERENCES "users" ("user_id") ON DELETE CASCADE,
    "cache_key" varchar(128) NOT NULL,
    "expires" timestamp with time zone DEFAULT NULL,
    "data" long NOT NULL,
    PRIMARY KEY ("user_id", "cache_key")
);

CREATE INDEX "cache_expires_idx" ON "cache" ("expires");


CREATE TABLE "cache_shared" (
    "cache_key" varchar(255) NOT NULL,
    "expires" timestamp with time zone DEFAULT NULL,
    "data" long NOT NULL,
    PRIMARY KEY ("cache_key")
);

CREATE INDEX "cache_shared_expires_idx" ON "cache_shared" ("expires");


CREATE TABLE "cache_index" (
    "user_id" integer NOT NULL
        REFERENCES "users" ("user_id") ON DELETE CASCADE,
    "mailbox" varchar(255) NOT NULL,
    "expires" timestamp with time zone DEFAULT NULL,
    "valid" smallint DEFAULT 0 NOT NULL,
    "data" long NOT NULL,
    PRIMARY KEY ("user_id", "mailbox")
);

CREATE INDEX "cache_index_expires_idx" ON "cache_index" ("expires");


CREATE TABLE "cache_thread" (
    "user_id" integer NOT NULL
        REFERENCES "users" ("user_id") ON DELETE CASCADE,
    "mailbox" varchar(255) NOT NULL,
    "expires" timestamp with time zone DEFAULT NULL,
    "data" long NOT NULL,
    PRIMARY KEY ("user_id", "mailbox")
);

CREATE INDEX "cache_thread_expires_idx" ON "cache_thread" ("expires");


CREATE TABLE "cache_messages" (
    "user_id" integer NOT NULL
        REFERENCES "users" ("user_id") ON DELETE CASCADE,
    "mailbox" varchar(255) NOT NULL,
    "uid" integer NOT NULL,
    "expires" timestamp with time zone DEFAULT NULL,
    "data" long NOT NULL,
    "flags" integer DEFAULT 0 NOT NULL,
    PRIMARY KEY ("user_id", "mailbox", "uid")
);

CREATE INDEX "cache_messages_expires_idx" ON "cache_messages" ("expires");


CREATE TABLE "dictionary" (
    "user_id" integer DEFAULT NULL
        REFERENCES "users" ("user_id") ON DELETE CASCADE,
    "language" varchar(5) NOT NULL,
    "data" long DEFAULT NULL,
    CONSTRAINT "dictionary_user_id_lang_key" UNIQUE ("user_id", "language")
);


CREATE TABLE "searches" (
    "search_id" integer PRIMARY KEY,
    "user_id" integer NOT NULL
        REFERENCES "users" ("user_id") ON DELETE CASCADE,
    "type" smallint DEFAULT 0 NOT NULL,
    "name" varchar(128) NOT NULL,
    "data" long NOT NULL,
    CONSTRAINT "searches_user_id_key" UNIQUE ("user_id", "type", "name")
);

CREATE SEQUENCE "searches_seq"
    START WITH 1 INCREMENT BY 1 NOMAXVALUE;

CREATE TRIGGER "searches_seq_trig"
BEFORE INSERT ON "searches" FOR EACH ROW
BEGIN
    :NEW."search_id" := "searches_seq".nextval;
END;
/

CREATE TABLE "system" (
    "name" varchar(64) NOT NULL PRIMARY KEY,
    "value" long
);

INSERT INTO "system" ("name", "value") VALUES ('roundcube-version', '2016112200');
