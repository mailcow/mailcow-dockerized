CREATE TABLE `cache_shared` (
 `cache_key` varchar(255) /*!40101 CHARACTER SET ascii COLLATE ascii_general_ci */ NOT NULL,
 `created` datetime NOT NULL DEFAULT '1000-01-01 00:00:00',
 `data` longtext NOT NULL,
 INDEX `created_index` (`created`),
 INDEX `cache_key_index` (`cache_key`)
) /*!40000 ENGINE=INNODB */ /*!40101 CHARACTER SET utf8 COLLATE utf8_general_ci */;
