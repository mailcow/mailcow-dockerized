-- Upgrade from 0.9-beta

CREATE TABLE IF NOT EXISTS `system` (
 `name` varchar(64) NOT NULL,
 `value` mediumtext,
 PRIMARY KEY(`name`)
) /*!40000 ENGINE=INNODB */ /*!40101 CHARACTER SET utf8 COLLATE utf8_general_ci */;
