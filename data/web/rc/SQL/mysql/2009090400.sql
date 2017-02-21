-- Updates from version 0.3-stable

TRUNCATE `messages`;

ALTER TABLE `messages`
    ADD INDEX `index_index` (`user_id`, `cache_key`, `idx`);

ALTER TABLE `session` 
    CHANGE `vars` `vars` MEDIUMTEXT NOT NULL;

ALTER TABLE `contacts`
    ADD INDEX `user_contacts_index` (`user_id`,`email`);
