-- Updates from version 0.6

/*!40014 SET FOREIGN_KEY_CHECKS=0 */;

ALTER TABLE `users` CHANGE `alias` `alias` varchar(128) BINARY NOT NULL;
ALTER TABLE `users` CHANGE `username` `username` varchar(128) BINARY NOT NULL;

CREATE TABLE `dictionary` (
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `language` varchar(5) NOT NULL,
  `data` longtext NOT NULL,
  CONSTRAINT `user_id_fk_dictionary` FOREIGN KEY (`user_id`)
    REFERENCES `users`(`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  UNIQUE `uniqueness` (`user_id`, `language`)
) /*!40000 ENGINE=INNODB */ /*!40101 CHARACTER SET utf8 COLLATE utf8_general_ci */;

CREATE TABLE `searches` (
  `search_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `type` int(3) NOT NULL DEFAULT '0',
  `name` varchar(128) NOT NULL,
  `data` text,
  PRIMARY KEY(`search_id`),
  CONSTRAINT `user_id_fk_searches` FOREIGN KEY (`user_id`)
    REFERENCES `users`(`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  UNIQUE `uniqueness` (`user_id`, `type`, `name`)
) /*!40000 ENGINE=INNODB */ /*!40101 CHARACTER SET utf8 COLLATE utf8_general_ci */;

DROP TABLE `messages`;

CREATE TABLE `cache_index` (
 `user_id` int(10) UNSIGNED NOT NULL DEFAULT '0',
 `mailbox` varchar(255) BINARY NOT NULL,
 `changed` datetime NOT NULL DEFAULT '1000-01-01 00:00:00',
 `valid` tinyint(1) NOT NULL DEFAULT '0',
 `data` longtext NOT NULL,
 CONSTRAINT `user_id_fk_cache_index` FOREIGN KEY (`user_id`)
   REFERENCES `users`(`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
 INDEX `changed_index` (`changed`),
 PRIMARY KEY (`user_id`, `mailbox`)
) /*!40000 ENGINE=INNODB */ /*!40101 CHARACTER SET utf8 COLLATE utf8_general_ci */;

CREATE TABLE `cache_thread` (
 `user_id` int(10) UNSIGNED NOT NULL DEFAULT '0',
 `mailbox` varchar(255) BINARY NOT NULL,
 `changed` datetime NOT NULL DEFAULT '1000-01-01 00:00:00',
 `data` longtext NOT NULL,
 CONSTRAINT `user_id_fk_cache_thread` FOREIGN KEY (`user_id`)
   REFERENCES `users`(`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
 INDEX `changed_index` (`changed`),
 PRIMARY KEY (`user_id`, `mailbox`)
) /*!40000 ENGINE=INNODB */ /*!40101 CHARACTER SET utf8 COLLATE utf8_general_ci */;

CREATE TABLE `cache_messages` (
 `user_id` int(10) UNSIGNED NOT NULL DEFAULT '0',
 `mailbox` varchar(255) BINARY NOT NULL,
 `uid` int(11) UNSIGNED NOT NULL DEFAULT '0',
 `changed` datetime NOT NULL DEFAULT '1000-01-01 00:00:00',
 `data` longtext NOT NULL,
 `flags` int(11) NOT NULL DEFAULT '0',
 CONSTRAINT `user_id_fk_cache_messages` FOREIGN KEY (`user_id`)
   REFERENCES `users`(`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
 INDEX `changed_index` (`changed`),
 PRIMARY KEY (`user_id`, `mailbox`, `uid`)
) /*!40000 ENGINE=INNODB */ /*!40101 CHARACTER SET utf8 COLLATE utf8_general_ci */;

/*!40014 SET FOREIGN_KEY_CHECKS=1 */;
