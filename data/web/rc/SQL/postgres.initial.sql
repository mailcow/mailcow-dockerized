-- Roundcube Webmail initial database structure

--
-- Sequence "users_seq"
-- Name: users_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE users_seq
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;

--
-- Table "users"
-- Name: users; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE users (
    user_id integer DEFAULT nextval('users_seq'::text) PRIMARY KEY,
    username varchar(128) DEFAULT '' NOT NULL,
    mail_host varchar(128) DEFAULT '' NOT NULL,
    created timestamp with time zone DEFAULT now() NOT NULL,
    last_login timestamp with time zone DEFAULT NULL,
    failed_login timestamp with time zone DEFAULT NULL,
    failed_login_counter integer DEFAULT NULL,
    "language" varchar(5),
    preferences text DEFAULT ''::text NOT NULL,
    CONSTRAINT users_username_key UNIQUE (username, mail_host)
);


--
-- Table "session"
-- Name: session; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE "session" (
    sess_id varchar(128) DEFAULT '' PRIMARY KEY,
    changed timestamp with time zone DEFAULT now() NOT NULL,
    ip varchar(41) NOT NULL,
    vars text NOT NULL
);

CREATE INDEX session_changed_idx ON session (changed);


--
-- Sequence "identities_seq"
-- Name: identities_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE identities_seq
    START WITH 1
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;

--
-- Table "identities"
-- Name: identities; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE identities (
    identity_id integer DEFAULT nextval('identities_seq'::text) PRIMARY KEY,
    user_id integer NOT NULL
        REFERENCES users (user_id) ON DELETE CASCADE ON UPDATE CASCADE,
    changed timestamp with time zone DEFAULT now() NOT NULL,
    del smallint DEFAULT 0 NOT NULL,
    standard smallint DEFAULT 0 NOT NULL,
    name varchar(128) NOT NULL,
    organization varchar(128),
    email varchar(128) NOT NULL,
    "reply-to" varchar(128),
    bcc varchar(128),
    signature text,
    html_signature integer DEFAULT 0 NOT NULL
);

CREATE INDEX identities_user_id_idx ON identities (user_id, del);
CREATE INDEX identities_email_idx ON identities (email, del);


--
-- Sequence "contacts_seq"
-- Name: contacts_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE contacts_seq
    START WITH 1
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;

--
-- Table "contacts"
-- Name: contacts; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE contacts (
    contact_id integer DEFAULT nextval('contacts_seq'::text) PRIMARY KEY,
    user_id integer NOT NULL
        REFERENCES users (user_id) ON DELETE CASCADE ON UPDATE CASCADE,
    changed timestamp with time zone DEFAULT now() NOT NULL,
    del smallint DEFAULT 0 NOT NULL,
    name varchar(128) DEFAULT '' NOT NULL,
    email text DEFAULT '' NOT NULL,
    firstname varchar(128) DEFAULT '' NOT NULL,
    surname varchar(128) DEFAULT '' NOT NULL,
    vcard text,
    words text
);

CREATE INDEX contacts_user_id_idx ON contacts (user_id, del);

--
-- Sequence "contactgroups_seq"
-- Name: contactgroups_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE contactgroups_seq
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;

--
-- Table "contactgroups"
-- Name: contactgroups; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE contactgroups (
    contactgroup_id integer DEFAULT nextval('contactgroups_seq'::text) PRIMARY KEY,
    user_id integer NOT NULL
        REFERENCES users(user_id) ON DELETE CASCADE ON UPDATE CASCADE,
    changed timestamp with time zone DEFAULT now() NOT NULL,
    del smallint NOT NULL DEFAULT 0,
    name varchar(128) NOT NULL DEFAULT ''
);

CREATE INDEX contactgroups_user_id_idx ON contactgroups (user_id, del);

--
-- Table "contactgroupmembers"
-- Name: contactgroupmembers; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE contactgroupmembers (
    contactgroup_id integer NOT NULL
        REFERENCES contactgroups(contactgroup_id) ON DELETE CASCADE ON UPDATE CASCADE,
    contact_id integer NOT NULL
        REFERENCES contacts(contact_id) ON DELETE CASCADE ON UPDATE CASCADE,
    created timestamp with time zone DEFAULT now() NOT NULL,
    PRIMARY KEY (contactgroup_id, contact_id)
);

CREATE INDEX contactgroupmembers_contact_id_idx ON contactgroupmembers (contact_id);

--
-- Table "cache"
-- Name: cache; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE "cache" (
    user_id integer NOT NULL
        REFERENCES users (user_id) ON DELETE CASCADE ON UPDATE CASCADE,
    cache_key varchar(128) DEFAULT '' NOT NULL,
    expires timestamp with time zone DEFAULT NULL,
    data text NOT NULL,
    PRIMARY KEY (user_id, cache_key)
);

CREATE INDEX cache_expires_idx ON "cache" (expires);

--
-- Table "cache_shared"
-- Name: cache_shared; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE "cache_shared" (
    cache_key varchar(255) NOT NULL PRIMARY KEY,
    expires timestamp with time zone DEFAULT NULL,
    data text NOT NULL
);

CREATE INDEX cache_shared_expires_idx ON "cache_shared" (expires);

--
-- Table "cache_index"
-- Name: cache_index; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE cache_index (
    user_id integer NOT NULL
        REFERENCES users (user_id) ON DELETE CASCADE ON UPDATE CASCADE,
    mailbox varchar(255) NOT NULL,
    expires timestamp with time zone DEFAULT NULL,
    valid smallint NOT NULL DEFAULT 0,
    data text NOT NULL,
    PRIMARY KEY (user_id, mailbox)
);

CREATE INDEX cache_index_expires_idx ON cache_index (expires);

--
-- Table "cache_thread"
-- Name: cache_thread; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE cache_thread (
    user_id integer NOT NULL
        REFERENCES users (user_id) ON DELETE CASCADE ON UPDATE CASCADE,
    mailbox varchar(255) NOT NULL,
    expires timestamp with time zone DEFAULT NULL,
    data text NOT NULL,
    PRIMARY KEY (user_id, mailbox)
);

CREATE INDEX cache_thread_expires_idx ON cache_thread (expires);

--
-- Table "cache_messages"
-- Name: cache_messages; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE cache_messages (
    user_id integer NOT NULL
        REFERENCES users (user_id) ON DELETE CASCADE ON UPDATE CASCADE,
    mailbox varchar(255) NOT NULL,
    uid integer NOT NULL,
    expires timestamp with time zone DEFAULT NULL,
    data text NOT NULL,
    flags integer NOT NULL DEFAULT 0,
    PRIMARY KEY (user_id, mailbox, uid)
);

CREATE INDEX cache_messages_expires_idx ON cache_messages (expires);

--
-- Table "dictionary"
-- Name: dictionary; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE dictionary (
    user_id integer DEFAULT NULL
        REFERENCES users (user_id) ON DELETE CASCADE ON UPDATE CASCADE,
   "language" varchar(5) NOT NULL,
    data text NOT NULL,
    CONSTRAINT dictionary_user_id_language_key UNIQUE (user_id, "language")
);

--
-- Sequence "searches_seq"
-- Name: searches_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE searches_seq
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;

--
-- Table "searches"
-- Name: searches; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE searches (
    search_id integer DEFAULT nextval('searches_seq'::text) PRIMARY KEY,
    user_id integer NOT NULL
        REFERENCES users (user_id) ON DELETE CASCADE ON UPDATE CASCADE,
    "type" smallint DEFAULT 0 NOT NULL,
    name varchar(128) NOT NULL,
    data text NOT NULL,
    CONSTRAINT searches_user_id_key UNIQUE (user_id, "type", name)
);


--
-- Table "system"
-- Name: system; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE "system" (
    name varchar(64) NOT NULL PRIMARY KEY,
    value text
);

INSERT INTO system (name, value) VALUES ('roundcube-version', '2016112200');
