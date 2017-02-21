-- Updates from version 0.7

CREATE TABLE contacts_tmp (
  contact_id integer NOT NULL PRIMARY KEY,
  user_id integer NOT NULL,
  changed datetime NOT NULL default '0000-00-00 00:00:00',
  del tinyint NOT NULL default '0',
  name varchar(128) NOT NULL default '',
  email text NOT NULL default '',
  firstname varchar(128) NOT NULL default '',
  surname varchar(128) NOT NULL default '',
  vcard text NOT NULL default '',
  words text NOT NULL default ''
);

INSERT INTO contacts_tmp (contact_id, user_id, changed, del, name, email, firstname, surname, vcard, words)
    SELECT contact_id, user_id, changed, del, name, email, firstname, surname, vcard, words FROM contacts;

DROP TABLE contacts;

CREATE TABLE contacts (
  contact_id integer NOT NULL PRIMARY KEY,
  user_id integer NOT NULL,
  changed datetime NOT NULL default '0000-00-00 00:00:00',
  del tinyint NOT NULL default '0',
  name varchar(128) NOT NULL default '',
  email text NOT NULL default '',
  firstname varchar(128) NOT NULL default '',
  surname varchar(128) NOT NULL default '',
  vcard text NOT NULL default '',
  words text NOT NULL default ''
);

INSERT INTO contacts (contact_id, user_id, changed, del, name, email, firstname, surname, vcard, words)
    SELECT contact_id, user_id, changed, del, name, email, firstname, surname, vcard, words FROM contacts_tmp;

CREATE INDEX ix_contacts_user_id ON contacts(user_id, del);
DROP TABLE contacts_tmp;
