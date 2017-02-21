-- Updates from version 0.5.x

ALTER TABLE contacts ADD words TEXT NULL;
CREATE INDEX contactgroupmembers_contact_id_idx ON contactgroupmembers (contact_id);

TRUNCATE messages;
TRUNCATE cache;
