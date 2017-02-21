-- Updates from version 0.3.1

-- ALTER TABLE identities ADD COLUMN changed datetime NOT NULL default '0000-00-00 00:00:00'; --

CREATE TABLE temp_identities (
  identity_id integer NOT NULL PRIMARY KEY,
  user_id integer NOT NULL default '0',
  standard tinyint NOT NULL default '0',
  name varchar(128) NOT NULL default '',
  organization varchar(128) default '',
  email varchar(128) NOT NULL default '',
  "reply-to" varchar(128) NOT NULL default '',
  bcc varchar(128) NOT NULL default '',
  signature text NOT NULL default '',
  html_signature tinyint NOT NULL default '0'
);
INSERT INTO temp_identities (identity_id, user_id, standard, name, organization, email, "reply-to", bcc, signature, html_signature)
  SELECT identity_id, user_id, standard, name, organization, email, "reply-to", bcc, signature, html_signature
  FROM identities WHERE del=0;

DROP INDEX ix_identities_user_id;
DROP TABLE identities;

CREATE TABLE identities (
  identity_id integer NOT NULL PRIMARY KEY,
  user_id integer NOT NULL default '0',
  changed datetime NOT NULL default '0000-00-00 00:00:00',
  del tinyint NOT NULL default '0',
  standard tinyint NOT NULL default '0',
  name varchar(128) NOT NULL default '',
  organization varchar(128) default '',
  email varchar(128) NOT NULL default '',
  "reply-to" varchar(128) NOT NULL default '',
  bcc varchar(128) NOT NULL default '',
  signature text NOT NULL default '',
  html_signature tinyint NOT NULL default '0'
);
CREATE INDEX ix_identities_user_id ON identities(user_id, del);

INSERT INTO identities (identity_id, user_id, standard, name, organization, email, "reply-to", bcc, signature, html_signature)
  SELECT identity_id, user_id, standard, name, organization, email, "reply-to", bcc, signature, html_signature
  FROM temp_identities;

DROP TABLE temp_identities;

CREATE TABLE contactgroups (
  contactgroup_id integer NOT NULL PRIMARY KEY,
  user_id integer NOT NULL default '0',
  changed datetime NOT NULL default '0000-00-00 00:00:00',
  del tinyint NOT NULL default '0',
  name varchar(128) NOT NULL default ''
);

CREATE INDEX ix_contactgroups_user_id ON contactgroups(user_id, del);

CREATE TABLE contactgroupmembers (
  contactgroup_id integer NOT NULL,
  contact_id integer NOT NULL default '0',
  created datetime NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY (contactgroup_id, contact_id)
);
