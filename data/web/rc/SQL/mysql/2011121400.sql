-- Updates from version 0.7

/*!40014 SET FOREIGN_KEY_CHECKS=0 */;

ALTER TABLE `contacts` DROP FOREIGN KEY `user_id_fk_contacts`;
ALTER TABLE `contacts` DROP INDEX `user_contacts_index`;
ALTER TABLE `contacts` MODIFY `email` text NOT NULL;
ALTER TABLE `contacts` ADD INDEX `user_contacts_index` (`user_id`,`del`);
ALTER TABLE `contacts` ADD CONSTRAINT `user_id_fk_contacts` FOREIGN KEY (`user_id`)
   REFERENCES `users`(`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `cache` ALTER `user_id` DROP DEFAULT;
ALTER TABLE `cache_index` ALTER `user_id` DROP DEFAULT;
ALTER TABLE `cache_thread` ALTER `user_id` DROP DEFAULT;
ALTER TABLE `cache_messages` ALTER `user_id` DROP DEFAULT;
ALTER TABLE `contacts` ALTER `user_id` DROP DEFAULT;
ALTER TABLE `contactgroups` ALTER `user_id` DROP DEFAULT;
ALTER TABLE `contactgroupmembers` ALTER `contact_id` DROP DEFAULT;
ALTER TABLE `identities` ALTER `user_id` DROP DEFAULT;
ALTER TABLE `searches` ALTER `user_id` DROP DEFAULT;

/*!40014 SET FOREIGN_KEY_CHECKS=1 */;
