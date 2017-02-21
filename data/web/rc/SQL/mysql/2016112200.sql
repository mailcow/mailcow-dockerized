ALTER TABLE `dictionary` ADD COLUMN `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST; -- redundant, for compat. with Galera Cluster

DROP TABLE `cache`;
DROP TABLE `cache_shared`;

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


CREATE TABLE `cache_shared` (
 `cache_key` varchar(255) /*!40101 CHARACTER SET ascii COLLATE ascii_general_ci */ NOT NULL,
 `expires` datetime DEFAULT NULL,
 `data` longtext NOT NULL,
 PRIMARY KEY (`cache_key`),
 INDEX `expires_index` (`expires`)
) /*!40000 ENGINE=INNODB */ /*!40101 CHARACTER SET utf8 COLLATE utf8_general_ci */;
