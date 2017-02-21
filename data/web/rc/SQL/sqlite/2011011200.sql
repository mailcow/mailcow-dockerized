-- Updates from version 0.5.x

CREATE TABLE contacts_tmp (
    contact_id integer NOT NULL PRIMARY KEY,
    user_id integer NOT NULL default '0',
    changed datetime NOT NULL default '0000-00-00 00:00:00',
    del tinyint NOT NULL default '0',
    name varchar(128) NOT NULL default '',
    email varchar(255) NOT NULL default '',
    firstname varchar(128) NOT NULL default '',
    surname varchar(128) NOT NULL default '',
    vcard text NOT NULL default ''
);

INSERT INTO contacts_tmp (contact_id, user_id, changed, del, name, email, firstname, surname, vcard)
    SELECT contact_id, user_id, changed, del, name, email, firstname, surname, vcard FROM contacts;

DROP TABLE contacts;
CREATE TABLE contacts (
    contact_id integer NOT NULL PRIMARY KEY,
    user_id integer NOT NULL default '0',
    changed datetime NOT NULL default '0000-00-00 00:00:00',
    del tinyint NOT NULL default '0',
    name varchar(128) NOT NULL default '',
    email varchar(255) NOT NULL default '',
    firstname varchar(128) NOT NULL default '',
    surname varchar(128) NOT NULL default '',
    vcard text NOT NULL default '',
    words text NOT NULL default ''
);

INSERT INTO contacts (contact_id, user_id, changed, del, name, email, firstname, surname, vcard)
    SELECT contact_id, user_id, changed, del, name, email, firstname, surname, vcard FROM contacts_tmp;

CREATE INDEX ix_contacts_user_id ON contacts(user_id, email);
DROP TABLE contacts_tmp;


DELETE FROM messages;
DELETE FROM cache;
CREATE INDEX ix_contactgroupmembers_contact_id ON contactgroupmembers (contact_id);
