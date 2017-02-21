ALTER TABLE `cache` ADD `expires` datetime DEFAULT NULL;
ALTER TABLE `cache_shared` ADD `expires` datetime DEFAULT NULL;
ALTER TABLE `cache_index` ADD `expires` datetime DEFAULT NULL;
ALTER TABLE `cache_thread` ADD `expires` datetime DEFAULT NULL;
ALTER TABLE `cache_messages` ADD `expires` datetime DEFAULT NULL;

-- initialize expires column with created/changed date + 7days
UPDATE `cache` SET `expires` = `created` + interval 604800 second;
UPDATE `cache_shared` SET `expires` = `created` + interval 604800 second;
UPDATE `cache_index` SET `expires` = `changed` + interval 604800 second;
UPDATE `cache_thread` SET `expires` = `changed` + interval 604800 second;
UPDATE `cache_messages` SET `expires` = `changed` + interval 604800 second;

ALTER TABLE `cache` DROP INDEX `created_index`;
ALTER TABLE `cache_shared` DROP INDEX `created_index`;
ALTER TABLE `cache_index` DROP `changed`;
ALTER TABLE `cache_thread` DROP `changed`;
ALTER TABLE `cache_messages` DROP `changed`;

ALTER TABLE `cache` ADD INDEX `expires_index` (`expires`);
ALTER TABLE `cache_shared` ADD INDEX `expires_index` (`expires`);
ALTER TABLE `cache_index` ADD INDEX `expires_index` (`expires`);
ALTER TABLE `cache_thread` ADD INDEX `expires_index` (`expires`);
ALTER TABLE `cache_messages` ADD INDEX `expires_index` (`expires`);
