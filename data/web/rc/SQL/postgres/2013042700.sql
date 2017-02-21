ALTER SEQUENCE user_ids RENAME TO users_seq;
ALTER TABLE users ALTER COLUMN user_id SET DEFAULT nextval('users_seq'::text);

ALTER SEQUENCE identity_ids RENAME TO identities_seq;
ALTER TABLE identities ALTER COLUMN identity_id SET DEFAULT nextval('identities_seq'::text);

ALTER SEQUENCE contact_ids RENAME TO contacts_seq;
ALTER TABLE contacts ALTER COLUMN contact_id SET DEFAULT nextval('contacts_seq'::text);

ALTER SEQUENCE contactgroups_ids RENAME TO contactgroups_seq;
ALTER TABLE contactgroups ALTER COLUMN contactgroup_id SET DEFAULT nextval('contactgroups_seq'::text);

ALTER SEQUENCE search_ids RENAME TO searches_seq;
ALTER TABLE searches ALTER COLUMN search_id SET DEFAULT nextval('searches_seq'::text);
