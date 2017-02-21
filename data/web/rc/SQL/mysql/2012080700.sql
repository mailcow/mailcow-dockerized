-- Updates from version 0.8

ALTER TABLE `cache` DROP COLUMN `cache_id`;
ALTER TABLE `users` DROP COLUMN `alias`;
ALTER TABLE `identities` ADD INDEX `email_identities_index` (`email`, `del`);
