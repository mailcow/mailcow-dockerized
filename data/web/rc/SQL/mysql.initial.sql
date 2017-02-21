-- Roundcube Webmail initial database structure


/*!40014  SET FOREIGN_KEY_CHECKS=0 */;

-- Table structure for table `session`

CREATE TABLE `session` (
 `sess_id` varchar(128) NOT NULL,
 `changed` datetime NOT NULL DEFAULT '1000-01-01 00:00:00',
 `ip` varchar(40) NOT NULL,
 `vars` mediumtext NOT NULL,
 PRIMARY KEY(`sess_id`),
 INDEX `changed_index` (`changed`)
) /*!40000 ENGINE=INNODB */ /*!40101 CHARACTER SET utf8 COLLATE utf8_general_ci */;


-- Table structure for table `users`

CREATE TABLE `users` (
 `user_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
 `username` varchar(128) BINARY NOT NULL,
 `mail_host` varchar(128) NOT NULL,
 `created` datetime NOT NULL DEFAULT '1000-01-01 00:00:00',
 `last_login` datetime DEFAULT NULL,
 `failed_login` datetime DEFAULT NULL,
 `failed_login_counter` int(10) UNSIGNED DEFAULT NULL,
 `language` varchar(5),
 `preferences` longtext,
 PRIMARY KEY(`user_id`),
 UNIQUE `username` (`username`, `mail_host`)
) /*!40000 ENGINE=INNODB */ /*!40101 CHARACTER SET utf8 COLLATE utf8_general_ci */;


-- Table structure for table `cache`

CREATE TABLE `cache` (
 `user_id` int(10) UNSIGNED NOT NULL,
 `cache_key` varchar(128) /*!40101 CHARACTER SET ascii COLLATE ascii_general_ci */ NOT NULL,
 `expires` datetime DEFAULT NULL,
 `data` longtext NOT NULL,
 PRIMARY KEY (`user_id`, `cache_key`),
 CONSTRAINT `user_id_fk_cache` FOREIGN KEY (`user_id`)
   REFERENCES `users`(`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
 INDEX `expires_index` (`expires`)
) /*!40000 ENGINE=INNODB */ /*!40101 CHARACTER SET utf8 COLLATE utf8_general_ci */;


-- Table structure for table `cache_shared`

CREATE TABLE `cache_shared` (
 `cache_key` varchar(255) /*!40101 CHARACTER SET ascii COLLATE ascii_general_ci */ NOT NULL,
 `expires` datetime DEFAULT NULL,
 `data` longtext NOT NULL,
 PRIMARY KEY (`cache_key`),
 INDEX `expires_index` (`expires`)
) /*!40000 ENGINE=INNODB */ /*!40101 CHARACTER SET utf8 COLLATE utf8_general_ci */;


-- Table structure for table `cache_index`

CREATE TABLE `cache_index` (
 `user_id` int(10) UNSIGNED NOT NULL,
 `mailbox` varchar(255) BINARY NOT NULL,
 `expires` datetime DEFAULT NULL,
 `valid` tinyint(1) NOT NULL DEFAULT '0',
 `data` longtext NOT NULL,
 CONSTRAINT `user_id_fk_cache_index` FOREIGN KEY (`user_id`)
   REFERENCES `users`(`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
 INDEX `expires_index` (`expires`),
 PRIMARY KEY (`user_id`, `mailbox`)
) /*!40000 ENGINE=INNODB */ /*!40101 CHARACTER SET utf8 COLLATE utf8_general_ci */;


-- Table structure for table `cache_thread`

CREATE TABLE `cache_thread` (
 `user_id` int(10) UNSIGNED NOT NULL,
 `mailbox` varchar(255) BINARY NOT NULL,
 `expires` datetime DEFAULT NULL,
 `data` longtext NOT NULL,
 CONSTRAINT `user_id_fk_cache_thread` FOREIGN KEY (`user_id`)
   REFERENCES `users`(`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
 INDEX `expires_index` (`expires`),
 PRIMARY KEY (`user_id`, `mailbox`)
) /*!40000 ENGINE=INNODB */ /*!40101 CHARACTER SET utf8 COLLATE utf8_general_ci */;


-- Table structure for table `cache_messages`

CREATE TABLE `cache_messages` (
 `user_id` int(10) UNSIGNED NOT NULL,
 `mailbox` varchar(255) BINARY NOT NULL,
 `uid` int(11) UNSIGNED NOT NULL DEFAULT '0',
 `expires` datetime DEFAULT NULL,
 `data` longtext NOT NULL,
 `flags` int(11) NOT NULL DEFAULT '0',
 CONSTRAINT `user_id_fk_cache_messages` FOREIGN KEY (`user_id`)
   REFERENCES `users`(`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
 INDEX `expires_index` (`expires`),
 PRIMARY KEY (`user_id`, `mailbox`, `uid`)
) /*!40000 ENGINE=INNODB */ /*!40101 CHARACTER SET utf8 COLLATE utf8_general_ci */;


-- Table structure for table `contacts`

CREATE TABLE `contacts` (
 `contact_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
 `changed` datetime NOT NULL DEFAULT '1000-01-01 00:00:00',
 `del` tinyint(1) NOT NULL DEFAULT '0',
 `name` varchar(128) NOT NULL DEFAULT '',
 `email` text NOT NULL,
 `firstname` varchar(128) NOT NULL DEFAULT '',
 `surname` varchar(128) NOT NULL DEFAULT '',
 `vcard` longtext NULL,
 `words` text NULL,
 `user_id` int(10) UNSIGNED NOT NULL,
 PRIMARY KEY(`contact_id`),
 CONSTRAINT `user_id_fk_contacts` FOREIGN KEY (`user_id`)
   REFERENCES `users`(`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
 INDEX `user_contacts_index` (`user_id`,`del`)
) /*!40000 ENGINE=INNODB */ /*!40101 CHARACTER SET utf8 COLLATE utf8_general_ci */;

-- Table structure for table `contactgroups`

CREATE TABLE `contactgroups` (
  `contactgroup_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(10) UNSIGNED NOT NULL,
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
  `contact_id` int(10) UNSIGNED NOT NULL,
  `created` datetime NOT NULL DEFAULT '1000-01-01 00:00:00',
  PRIMARY KEY (`contactgroup_id`, `contact_id`),
  CONSTRAINT `contactgroup_id_fk_contactgroups` FOREIGN KEY (`contactgroup_id`)
    REFERENCES `contactgroups`(`contactgroup_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `contact_id_fk_contacts` FOREIGN KEY (`contact_id`)
    REFERENCES `contacts`(`contact_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX `contactgroupmembers_contact_index` (`contact_id`)
) /*!40000 ENGINE=INNODB */;


-- Table structure for table `identities`

CREATE TABLE `identities` (
 `identity_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
 `user_id` int(10) UNSIGNED NOT NULL,
 `changed` datetime NOT NULL DEFAULT '1000-01-01 00:00:00',
 `del` tinyint(1) NOT NULL DEFAULT '0',
 `standard` tinyint(1) NOT NULL DEFAULT '0',
 `name` varchar(128) NOT NULL,
 `organization` varchar(128) NOT NULL DEFAULT '',
 `email` varchar(128) NOT NULL,
 `reply-to` varchar(128) NOT NULL DEFAULT '',
 `bcc` varchar(128) NOT NULL DEFAULT '',
 `signature` longtext,
 `html_signature` tinyint(1) NOT NULL DEFAULT '0',
 PRIMARY KEY(`identity_id`),
 CONSTRAINT `user_id_fk_identities` FOREIGN KEY (`user_id`)
   REFERENCES `users`(`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
 INDEX `user_identities_index` (`user_id`, `del`),
 INDEX `email_identities_index` (`email`, `del`)
) /*!40000 ENGINE=INNODB */ /*!40101 CHARACTER SET utf8 COLLATE utf8_general_ci */;


-- Table structure for table `dictionary`

CREATE TABLE `dictionary` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, -- redundant, for compat. with Galera Cluster
  `user_id` int(10) UNSIGNED DEFAULT NULL, -- NULL here is for "shared dictionaries"
  `language` varchar(5) NOT NULL,
  `data` longtext NOT NULL,
  CONSTRAINT `user_id_fk_dictionary` FOREIGN KEY (`user_id`)
    REFERENCES `users`(`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  UNIQUE `uniqueness` (`user_id`, `language`)
) /*!40000 ENGINE=INNODB */ /*!40101 CHARACTER SET utf8 COLLATE utf8_general_ci */;


-- Table structure for table `searches`

CREATE TABLE `searches` (
 `search_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
 `user_id` int(10) UNSIGNED NOT NULL,
 `type` int(3) NOT NULL DEFAULT '0',
 `name` varchar(128) NOT NULL,
 `data` text,
 PRIMARY KEY(`search_id`),
 CONSTRAINT `user_id_fk_searches` FOREIGN KEY (`user_id`)
   REFERENCES `users`(`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
 UNIQUE `uniqueness` (`user_id`, `type`, `name`)
) /*!40000 ENGINE=INNODB */ /*!40101 CHARACTER SET utf8 COLLATE utf8_general_ci */;


-- Table structure for table `system`

CREATE TABLE `system` (
 `name` varchar(64) NOT NULL,
 `value` mediumtext,
 PRIMARY KEY(`name`)
) /*!40000 ENGINE=INNODB */ /*!40101 CHARACTER SET utf8 COLLATE utf8_general_ci */;

/*!40014 SET FOREIGN_KEY_CHECKS=1 */;

INSERT INTO system (name, value) VALUES ('roundcube-version', '2016112200');
