-- Updates from version 0.3.1

DROP INDEX identities_user_id_idx;
CREATE INDEX identities_user_id_idx ON identities (user_id, del);

ALTER TABLE identities ADD changed timestamp with time zone DEFAULT now() NOT NULL;

CREATE SEQUENCE contactgroups_ids
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;

CREATE TABLE contactgroups (
    contactgroup_id integer DEFAULT nextval('contactgroups_ids'::text) PRIMARY KEY,
    user_id integer NOT NULL
        REFERENCES users(user_id) ON DELETE CASCADE ON UPDATE CASCADE,
    changed timestamp with time zone DEFAULT now() NOT NULL,
    del smallint NOT NULL DEFAULT 0,
    name varchar(128) NOT NULL DEFAULT ''
);

CREATE INDEX contactgroups_user_id_idx ON contactgroups (user_id, del);

CREATE TABLE contactgroupmembers (
    contactgroup_id integer NOT NULL
        REFERENCES contactgroups(contactgroup_id) ON DELETE CASCADE ON UPDATE CASCADE,
    contact_id integer NOT NULL
        REFERENCES contacts(contact_id) ON DELETE CASCADE ON UPDATE CASCADE,
    created timestamp with time zone DEFAULT now() NOT NULL,
    PRIMARY KEY (contactgroup_id, contact_id)
);
