-- Updates from version 0.4-beta

ALTER TABLE users ALTER last_login DROP NOT NULL;
ALTER TABLE users ALTER last_login SET DEFAULT NULL;
