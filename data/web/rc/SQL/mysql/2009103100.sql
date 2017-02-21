-- Updates from version 0.3.1
-- WARNING: Make sure that all tables are using InnoDB engine!!!
--          If not, use: ALTER TABLE xxx ENGINE=InnoDB;

/* MySQL bug workaround: http://bugs.mysql.com/bug.php?id=46293 */
/*!40014 SET FOREIGN_KEY_CHECKS=0 */;

ALTER TABLE `messages` DROP FOREIGN KEY `user_id_fk_messages`;
ALTER TABLE `cache` DROP FOREIGN KEY `user_id_fk_cache`;
ALTER TABLE `contacts` DROP FOREIGN KEY `user_id_fk_contacts`;
ALTER TABLE `identities` DROP FOREIGN KEY `user_id_fk_identities`;

ALTER TABLE `messages` ADD CONSTRAINT `user_id_fk_messages` FOREIGN KEY (`user_id`)
 REFERENCES `users`(`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `cache` ADD CONSTRAINT `user_id_fk_cache` FOREIGN KEY (`user_id`)
 REFERENCES `users`(`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `contacts` ADD CONSTRAINT `user_id_fk_contacts` FOREIGN KEY (`user_id`)
 REFERENCES `users`(`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `identities` ADD CONSTRAINT `user_id_fk_identities` FOREIGN KEY (`user_id`)
 REFERENCES `users`(`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `contacts` ALTER `name` SET DEFAULT '';
ALTER TABLE `contacts` ALTER `firstname` SET DEFAULT '';
ALTER TABLE `contacts` ALTER `surname` SET DEFAULT '';

ALTER TABLE `identities` ADD INDEX `user_identities_index` (`user_id`, `del`);
ALTER TABLE `identities` ADD `changed` datetime NOT NULL DEFAULT '1000-01-01 00:00:00' AFTER `user_id`;

CREATE TABLE `contactgroups` (
  `contactgroup_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `changed` datetime NOT NULL DEFAULT '1000-01-01 00:00:00',
  `del` tinyint(1) NOT NULL DEFAULT '0',
  `name` varchar(128) NOT NULL DEFAULT '',
  PRIMARY KEY(`contactgroup_id`),
  CONSTRAINT `user_id_fk_contactgroups` FOREIGN KEY (`user_id`)
    REFERENCES `users`(`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX `contactgroups_user_index` (`user_id`,`del`)
) /*!40000 ENGINE=INNODB */ /*!40101 CHARACTER SET utf8 COLLATE utf8_general_ci */;

CREATE TABLE `contactgroupmembers` (
  `contactgroup_id` int(10) UNSIGNED NOT NULL,
  `contact_id` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `created` datetime NOT NULL DEFAULT '1000-01-01 00:00:00',
  PRIMARY KEY (`contactgroup_id`, `contact_id`),
  CONSTRAINT `contactgroup_id_fk_contactgroups` FOREIGN KEY (`contactgroup_id`)
    REFERENCES `contactgroups`(`contactgroup_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `contact_id_fk_contacts` FOREIGN KEY (`contact_id`)
    REFERENCES `contacts`(`contact_id`) ON DELETE CASCADE ON UPDATE CASCADE
) /*!40000 ENGINE=INNODB */;

/*!40014 SET FOREIGN_KEY_CHECKS=1 */;
