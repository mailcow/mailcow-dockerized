-- Updates from version 0.5.x

ALTER TABLE `contacts` ADD `words` TEXT NULL AFTER `vcard`;
ALTER TABLE `contacts` CHANGE `vcard` `vcard` LONGTEXT /*!40101 CHARACTER SET utf8 */ NULL DEFAULT NULL;
ALTER TABLE `contactgroupmembers` ADD INDEX `contactgroupmembers_contact_index` (`contact_id`);

TRUNCATE TABLE `messages`;
TRUNCATE TABLE `cache`;
