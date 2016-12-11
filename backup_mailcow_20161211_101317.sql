-- MySQL dump 10.13  Distrib 5.5.53, for linux2.6 (x86_64)
--
-- Host: localhost    Database: mailcow
-- ------------------------------------------------------
-- Server version	5.5.53

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `admin`
--

DROP TABLE IF EXISTS `admin`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admin` (
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `superadmin` tinyint(1) NOT NULL DEFAULT '0',
  `created` datetime NOT NULL DEFAULT '2016-01-01 00:00:00',
  `modified` datetime NOT NULL DEFAULT '2016-01-01 00:00:00',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin`
--

LOCK TABLES `admin` WRITE;
/*!40000 ALTER TABLE `admin` DISABLE KEYS */;
INSERT INTO `admin` VALUES ('admin','{SSHA256}SQjVjgx1RK3MNBYYr0MelTY8D+BN04WJI96dXojaGRpiYjQ2MDVmODM5ZjQ3MzI4',1,'2016-12-09 17:53:39','2016-12-09 22:39:23',1);
/*!40000 ALTER TABLE `admin` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `alias`
--

DROP TABLE IF EXISTS `alias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `alias` (
  `address` varchar(255) NOT NULL,
  `goto` text NOT NULL,
  `domain` varchar(255) NOT NULL,
  `created` datetime NOT NULL DEFAULT '2016-01-01 00:00:00',
  `modified` datetime NOT NULL DEFAULT '2016-01-01 00:00:00',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`address`),
  KEY `domain` (`domain`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `alias`
--

LOCK TABLES `alias` WRITE;
/*!40000 ALTER TABLE `alias` DISABLE KEYS */;
INSERT INTO `alias` VALUES ('@mailcow.de','andre@mailcow.de','mailcow.de','2016-12-09 22:43:38','2016-12-09 22:43:38',1),('andre@mailcow.de','andre@mailcow.de','mailcow.de','2016-12-09 17:55:01','2016-12-09 17:55:01',1),('asd@mailcow.de','andre@mailcow.de','mailcow.de','2016-12-09 22:43:27','2016-12-09 22:43:27',1),('user1@mailcow.de','user1@mailcow.de','mailcow.de','2016-12-11 09:02:24','2016-12-11 09:02:24',1),('user2@mailcow.de','user2@mailcow.de','mailcow.de','2016-12-11 09:02:34','2016-12-11 09:02:34',1);
/*!40000 ALTER TABLE `alias` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `alias_domain`
--

DROP TABLE IF EXISTS `alias_domain`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `alias_domain` (
  `alias_domain` varchar(255) NOT NULL,
  `target_domain` varchar(255) NOT NULL,
  `created` datetime NOT NULL DEFAULT '2016-01-01 00:00:00',
  `modified` datetime NOT NULL DEFAULT '2016-01-01 00:00:00',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`alias_domain`),
  KEY `active` (`active`),
  KEY `target_domain` (`target_domain`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `alias_domain`
--

LOCK TABLES `alias_domain` WRITE;
/*!40000 ALTER TABLE `alias_domain` DISABLE KEYS */;
INSERT INTO `alias_domain` VALUES ('miau.de','mailcow.de','2016-12-09 22:39:30','2016-12-09 22:39:30',1);
/*!40000 ALTER TABLE `alias_domain` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `domain`
--

DROP TABLE IF EXISTS `domain`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `domain` (
  `domain` varchar(255) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `aliases` int(10) NOT NULL DEFAULT '0',
  `mailboxes` int(10) NOT NULL DEFAULT '0',
  `maxquota` bigint(20) NOT NULL DEFAULT '0',
  `quota` bigint(20) NOT NULL DEFAULT '0',
  `transport` varchar(255) NOT NULL,
  `backupmx` tinyint(1) NOT NULL DEFAULT '0',
  `relay_all_recipients` tinyint(1) NOT NULL DEFAULT '0',
  `created` datetime NOT NULL DEFAULT '2016-01-01 00:00:00',
  `modified` datetime NOT NULL DEFAULT '2016-01-01 00:00:00',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`domain`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `domain`
--

LOCK TABLES `domain` WRITE;
/*!40000 ALTER TABLE `domain` DISABLE KEYS */;
INSERT INTO `domain` VALUES ('domain2.de','domain2.de',400,10,3072,10240,'virtual',0,0,'2016-12-11 09:03:11','2016-12-11 09:03:11',1),('domain3.de','domain2.de',400,10,3072,10240,'virtual',1,1,'2016-12-11 09:03:27','2016-12-11 09:05:04',1),('mailcow.de','我世誰',400,10,3072,10240,'virtual',0,0,'2016-12-09 17:54:40','2016-12-09 17:54:46',1);
/*!40000 ALTER TABLE `domain` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `domain_admins`
--

DROP TABLE IF EXISTS `domain_admins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `domain_admins` (
  `username` varchar(255) NOT NULL,
  `domain` varchar(255) NOT NULL,
  `created` datetime NOT NULL DEFAULT '2016-01-01 00:00:00',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `domain_admins`
--

LOCK TABLES `domain_admins` WRITE;
/*!40000 ALTER TABLE `domain_admins` DISABLE KEYS */;
INSERT INTO `domain_admins` VALUES ('admin','ALL','2016-12-09 17:53:39',1);
/*!40000 ALTER TABLE `domain_admins` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `filterconf`
--

DROP TABLE IF EXISTS `filterconf`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `filterconf` (
  `object` varchar(100) NOT NULL DEFAULT '',
  `option` varchar(50) NOT NULL DEFAULT '',
  `value` varchar(100) NOT NULL DEFAULT '',
  `prefid` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`prefid`),
  KEY `object` (`object`)
) ENGINE=InnoDB AUTO_INCREMENT=93 DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `filterconf`
--

LOCK TABLES `filterconf` WRITE;
/*!40000 ALTER TABLE `filterconf` DISABLE KEYS */;
INSERT INTO `filterconf` VALUES ('andre@mailcow.de','highspamlevel','25',90),('andre@mailcow.de','lowspamlevel','7',91);
/*!40000 ALTER TABLE `filterconf` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Temporary table structure for view `grouped_domain_alias_address`
--

DROP TABLE IF EXISTS `grouped_domain_alias_address`;
/*!50001 DROP VIEW IF EXISTS `grouped_domain_alias_address`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE TABLE `grouped_domain_alias_address` (
  `username` tinyint NOT NULL,
  `ad_alias` tinyint NOT NULL
) ENGINE=MyISAM */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `grouped_mail_aliases`
--

DROP TABLE IF EXISTS `grouped_mail_aliases`;
/*!50001 DROP VIEW IF EXISTS `grouped_mail_aliases`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE TABLE `grouped_mail_aliases` (
  `username` tinyint NOT NULL,
  `aliases` tinyint NOT NULL
) ENGINE=MyISAM */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `grouped_sender_acl`
--

DROP TABLE IF EXISTS `grouped_sender_acl`;
/*!50001 DROP VIEW IF EXISTS `grouped_sender_acl`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE TABLE `grouped_sender_acl` (
  `username` tinyint NOT NULL,
  `send_as` tinyint NOT NULL
) ENGINE=MyISAM */;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `mailbox`
--

DROP TABLE IF EXISTS `mailbox`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mailbox` (
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `maildir` varchar(255) NOT NULL,
  `quota` bigint(20) NOT NULL DEFAULT '0',
  `local_part` varchar(255) NOT NULL,
  `domain` varchar(255) NOT NULL,
  `created` datetime NOT NULL DEFAULT '2016-01-01 00:00:00',
  `modified` datetime NOT NULL DEFAULT '2016-01-01 00:00:00',
  `tls_enforce_in` tinyint(1) NOT NULL DEFAULT '0',
  `tls_enforce_out` tinyint(1) NOT NULL DEFAULT '0',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`username`),
  KEY `domain` (`domain`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `mailbox`
--

LOCK TABLES `mailbox` WRITE;
/*!40000 ALTER TABLE `mailbox` DISABLE KEYS */;
INSERT INTO `mailbox` VALUES ('andre@mailcow.de','{SSHA256}E8aCNPMTbbUmjNw9JH1zckwXZEfJYiLOTmMRc9o1Uw0zZGU1MDU0YzcxNWRiMmYz','André 我世誰','mailcow.de/andre/',1073741824,'andre','mailcow.de','2016-12-09 17:55:01','2016-12-09 18:47:12',0,0,1),('user1@mailcow.de','{SSHA256}USFPl3nkKo3U3zPR8wQORxvKKUfUT8dkKzivKbFJYTxhMzBiN2NmYTk5OTZiM2Q3','user1','mailcow.de/user1/',128974848,'user1','mailcow.de','2016-12-11 09:02:24','2016-12-11 09:02:24',0,0,1),('user2@mailcow.de','{SSHA256}A7gh/EhI8Rl0MUYBhpt3ah4ohVIFoimQO8O61yULKqc1NjhmMmUyZmU0NGU1NTZh','user2','mailcow.de/user2/',128974848,'user2','mailcow.de','2016-12-11 09:02:34','2016-12-11 09:02:34',0,0,1);
/*!40000 ALTER TABLE `mailbox` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quota2`
--

DROP TABLE IF EXISTS `quota2`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `quota2` (
  `username` varchar(100) NOT NULL,
  `bytes` bigint(20) NOT NULL DEFAULT '0',
  `messages` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quota2`
--

LOCK TABLES `quota2` WRITE;
/*!40000 ALTER TABLE `quota2` DISABLE KEYS */;
INSERT INTO `quota2` VALUES ('andre@mailcow.de',4045,3),('user1@mailcow.de',0,0),('user2@mailcow.de',0,0);
/*!40000 ALTER TABLE `quota2` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sender_acl`
--

DROP TABLE IF EXISTS `sender_acl`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sender_acl` (
  `logged_in_as` varchar(255) NOT NULL,
  `send_as` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sender_acl`
--

LOCK TABLES `sender_acl` WRITE;
/*!40000 ALTER TABLE `sender_acl` DISABLE KEYS */;
/*!40000 ALTER TABLE `sender_acl` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sogo_acl`
--

DROP TABLE IF EXISTS `sogo_acl`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sogo_acl` (
  `c_folder_id` int(11) NOT NULL,
  `c_object` varchar(255) NOT NULL,
  `c_uid` varchar(255) NOT NULL,
  `c_role` varchar(80) NOT NULL,
  KEY `sogo_acl_c_folder_id_idx` (`c_folder_id`),
  KEY `sogo_acl_c_uid_idx` (`c_uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sogo_acl`
--

LOCK TABLES `sogo_acl` WRITE;
/*!40000 ALTER TABLE `sogo_acl` DISABLE KEYS */;
/*!40000 ALTER TABLE `sogo_acl` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sogo_alarms_folder`
--

DROP TABLE IF EXISTS `sogo_alarms_folder`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sogo_alarms_folder` (
  `c_path` varchar(255) NOT NULL,
  `c_name` varchar(255) NOT NULL,
  `c_uid` varchar(255) NOT NULL,
  `c_recurrence_id` int(11) DEFAULT NULL,
  `c_alarm_number` int(11) NOT NULL,
  `c_alarm_date` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sogo_alarms_folder`
--

LOCK TABLES `sogo_alarms_folder` WRITE;
/*!40000 ALTER TABLE `sogo_alarms_folder` DISABLE KEYS */;
/*!40000 ALTER TABLE `sogo_alarms_folder` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sogo_cache_folder`
--

DROP TABLE IF EXISTS `sogo_cache_folder`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sogo_cache_folder` (
  `c_uid` varchar(255) NOT NULL,
  `c_path` varchar(255) NOT NULL,
  `c_parent_path` varchar(255) DEFAULT NULL,
  `c_type` tinyint(3) unsigned NOT NULL,
  `c_creationdate` int(11) NOT NULL,
  `c_lastmodified` int(11) NOT NULL,
  `c_version` int(11) NOT NULL DEFAULT '0',
  `c_deleted` tinyint(4) NOT NULL DEFAULT '0',
  `c_content` longtext,
  PRIMARY KEY (`c_uid`,`c_path`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sogo_cache_folder`
--

LOCK TABLES `sogo_cache_folder` WRITE;
/*!40000 ALTER TABLE `sogo_cache_folder` DISABLE KEYS */;
/*!40000 ALTER TABLE `sogo_cache_folder` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sogo_folder_info`
--

DROP TABLE IF EXISTS `sogo_folder_info`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sogo_folder_info` (
  `c_folder_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `c_path` varchar(255) NOT NULL,
  `c_path1` varchar(255) NOT NULL,
  `c_path2` varchar(255) DEFAULT NULL,
  `c_path3` varchar(255) DEFAULT NULL,
  `c_path4` varchar(255) DEFAULT NULL,
  `c_foldername` varchar(255) NOT NULL,
  `c_location` varchar(2048) DEFAULT NULL,
  `c_quick_location` varchar(2048) DEFAULT NULL,
  `c_acl_location` varchar(2048) DEFAULT NULL,
  `c_folder_type` varchar(255) NOT NULL,
  PRIMARY KEY (`c_path`),
  UNIQUE KEY `c_folder_id` (`c_folder_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sogo_folder_info`
--

LOCK TABLES `sogo_folder_info` WRITE;
/*!40000 ALTER TABLE `sogo_folder_info` DISABLE KEYS */;
INSERT INTO `sogo_folder_info` VALUES (1,'/Users/andre@mailcow.de/Calendar/personal','Users','andre@mailcow.de','Calendar','personal','Persönlicher Kalender','mysql://mailcow:mysafepasswd@mysql:3306/mailcow/sogoandremai001621d4f34','mysql://mailcow:mysafepasswd@mysql:3306/mailcow/sogoandremai001621d4f34_quick','mysql://mailcow:mysafepasswd@mysql:3306/mailcow/sogoandremai001621d4f34_acl','Appointment'),(2,'/Users/andre@mailcow.de/Contacts/personal','Users','andre@mailcow.de','Contacts','personal','Persönliches Adressbuch','mysql://mailcow:mysafepasswd@mysql:3306/mailcow/sogoandremai0015873bdc7','mysql://mailcow:mysafepasswd@mysql:3306/mailcow/sogoandremai0015873bdc7_quick','mysql://mailcow:mysafepasswd@mysql:3306/mailcow/sogoandremai0015873bdc7_acl','Contact');
/*!40000 ALTER TABLE `sogo_folder_info` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sogo_quick_appointment`
--

DROP TABLE IF EXISTS `sogo_quick_appointment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sogo_quick_appointment` (
  `c_folder_id` int(11) NOT NULL,
  `c_name` varchar(255) NOT NULL,
  `c_uid` varchar(255) NOT NULL,
  `c_startdate` int(11) DEFAULT NULL,
  `c_enddate` int(11) DEFAULT NULL,
  `c_cycleenddate` int(11) DEFAULT NULL,
  `c_title` varchar(1000) NOT NULL,
  `c_participants` text,
  `c_isallday` int(11) DEFAULT NULL,
  `c_iscycle` int(11) DEFAULT NULL,
  `c_cycleinfo` text,
  `c_classification` int(11) NOT NULL,
  `c_isopaque` int(11) NOT NULL,
  `c_status` int(11) NOT NULL,
  `c_priority` int(11) DEFAULT NULL,
  `c_location` varchar(255) DEFAULT NULL,
  `c_orgmail` varchar(255) DEFAULT NULL,
  `c_partmails` text,
  `c_partstates` text,
  `c_category` varchar(255) DEFAULT NULL,
  `c_sequence` int(11) DEFAULT NULL,
  `c_component` varchar(10) NOT NULL,
  `c_nextalarm` int(11) DEFAULT NULL,
  `c_description` text,
  PRIMARY KEY (`c_folder_id`,`c_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sogo_quick_appointment`
--

LOCK TABLES `sogo_quick_appointment` WRITE;
/*!40000 ALTER TABLE `sogo_quick_appointment` DISABLE KEYS */;
/*!40000 ALTER TABLE `sogo_quick_appointment` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sogo_quick_contact`
--

DROP TABLE IF EXISTS `sogo_quick_contact`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sogo_quick_contact` (
  `c_folder_id` int(11) NOT NULL,
  `c_name` varchar(255) NOT NULL,
  `c_givenname` varchar(255) DEFAULT NULL,
  `c_cn` varchar(255) DEFAULT NULL,
  `c_sn` varchar(255) DEFAULT NULL,
  `c_screenname` varchar(255) DEFAULT NULL,
  `c_l` varchar(255) DEFAULT NULL,
  `c_mail` varchar(255) DEFAULT NULL,
  `c_o` varchar(255) DEFAULT NULL,
  `c_ou` varchar(255) DEFAULT NULL,
  `c_telephonenumber` varchar(255) DEFAULT NULL,
  `c_categories` varchar(255) DEFAULT NULL,
  `c_component` varchar(10) NOT NULL,
  PRIMARY KEY (`c_folder_id`,`c_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sogo_quick_contact`
--

LOCK TABLES `sogo_quick_contact` WRITE;
/*!40000 ALTER TABLE `sogo_quick_contact` DISABLE KEYS */;
/*!40000 ALTER TABLE `sogo_quick_contact` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sogo_sessions_folder`
--

DROP TABLE IF EXISTS `sogo_sessions_folder`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sogo_sessions_folder` (
  `c_id` varchar(255) NOT NULL,
  `c_value` varchar(255) NOT NULL,
  `c_creationdate` int(11) NOT NULL,
  `c_lastseen` int(11) NOT NULL,
  PRIMARY KEY (`c_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sogo_sessions_folder`
--

LOCK TABLES `sogo_sessions_folder` WRITE;
/*!40000 ALTER TABLE `sogo_sessions_folder` DISABLE KEYS */;
/*!40000 ALTER TABLE `sogo_sessions_folder` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sogo_store`
--

DROP TABLE IF EXISTS `sogo_store`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sogo_store` (
  `c_folder_id` int(11) NOT NULL,
  `c_name` varchar(255) NOT NULL DEFAULT '',
  `c_content` mediumtext NOT NULL,
  `c_creationdate` int(11) NOT NULL,
  `c_lastmodified` int(11) NOT NULL,
  `c_version` int(11) NOT NULL,
  `c_deleted` int(11) DEFAULT NULL,
  PRIMARY KEY (`c_folder_id`,`c_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sogo_store`
--

LOCK TABLES `sogo_store` WRITE;
/*!40000 ALTER TABLE `sogo_store` DISABLE KEYS */;
/*!40000 ALTER TABLE `sogo_store` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sogo_user_profile`
--

DROP TABLE IF EXISTS `sogo_user_profile`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sogo_user_profile` (
  `c_uid` varchar(255) NOT NULL,
  `c_defaults` text,
  `c_settings` text,
  PRIMARY KEY (`c_uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sogo_user_profile`
--

LOCK TABLES `sogo_user_profile` WRITE;
/*!40000 ALTER TABLE `sogo_user_profile` DISABLE KEYS */;
INSERT INTO `sogo_user_profile` VALUES ('andre@mailcow.de','{\"SOGoMailReceiptNonRecipientAction\": \"ignore\", \"SOGoMailAutoSave\": 0, \"SOGoMailLabelsColors\": {\"$label5\": [\"Später\", \"#993399\"], \"$label2\": [\"Geschäftlich\", \"#FF9900\"], \"$label4\": [\"To-Do\", \"#3333FF\"], \"$label1\": [\"Wichtig\", \"#FF0000\"], \"$label3\": [\"Persönlich\", \"#009900\"]}, \"SOGoTimeFormat\": \"%H:%M\", \"SOGoAppointmentSendEMailNotifications\": 1, \"SOGoCalendarCategories\": [\"Fragen\", \"Feiertag\", \"Geburtstag\", \"Klienten\", \"Jubiläum\", \"Kunde\", \"Besprechung\", \"Anrufe\", \"Verschiedenes\", \"Urlaub\", \"Persönlich\", \"Status\", \"Geschenke\", \"Ferien\", \"Konkurrenz\", \"Favoriten\", \"Ideen\", \"Lieferanten\", \"Reise\", \"Fortsetzung\", \"Geschäft\", \"Projekte\"], \"SOGoCalendarDefaultReminder\": \"NONE\", \"SOGoCalendarCategoriesColors\": {\"Lieferanten\": \"#CCCCCC\", \"Geschäft\": \"#CCCCCC\", \"Ideen\": \"#CCCCCC\", \"Ferien\": \"#CCCCCC\", \"Persönlich\": \"#CCCCCC\", \"Geburtstag\": \"#CCCCCC\", \"Projekte\": \"#CCCCCC\", \"Urlaub\": \"#CCCCCC\", \"Fragen\": \"#CCCCCC\", \"Konkurrenz\": \"#CCCCCC\", \"Jubiläum\": \"#CCCCCC\", \"Status\": \"#CCCCCC\", \"Feiertag\": \"#FFCC33\", \"Klienten\": \"#CCCCCC\", \"Fortsetzung\": \"#CCCCCC\", \"Anrufe\": \"#CCCCCC\", \"Reise\": \"#CCCCCC\", \"Verschiedenes\": \"#CCCCCC\", \"Besprechung\": \"#CCCCCC\", \"Geschenke\": \"#CCCCCC\", \"Kunde\": \"#CCCCCC\", \"Favoriten\": \"#CCCCCC\"}, \"SOGoMailReceiptOutsideDomainAction\": \"ignore\", \"SOGoRememberLastModule\": 0, \"SOGoMailReceiptAnyAction\": \"ignore\", \"SOGoLoginModule\": \"Mail\", \"Vacation\": {\"daysBetweenResponse\": 7, \"autoReplyEmailAddresses\": [\"andre@mailcow.de\"], \"endDate\": 0, \"startDateEnabled\": 0, \"endDateEnabled\": 0, \"startDate\": 0}, \"SOGoDayStartTime\": \"08:00\", \"SOGoCalendarWeekdays\": [\"SU\", \"MO\", \"TU\", \"WE\", \"TH\", \"FR\", \"SA\"], \"SOGoCalendarTasksDefaultClassification\": \"PUBLIC\", \"SOGoTimeZone\": \"Europe\\/Berlin\", \"SOGoMailReceiptAllow\": \"1\", \"SOGoRefreshViewCheck\": \"manually\", \"SOGoLanguage\": \"German\", \"SOGoMailCustomFullName\": \"André 字是疯狂的\", \"LocaleCode\": \"de\", \"SOGoMailSignature\": \"\", \"SOGoMailSignaturePlacement\": \"below\", \"SOGoSelectedAddressBook\": \"personal\", \"SOGoContactsCategories\": [\" Freund\", \" Geschäftspartner\", \" Kollegin\", \" Konkurrenten\", \" Kunden\", \" Lieferant\", \" Presse\", \" VIP\", \"Familie\"], \"SOGoShortDateFormat\": \"%d-%b-%y\", \"SOGoFirstWeekOfYear\": \"January1\", \"SOGoFirstDayOfWeek\": 1, \"SOGoAlternateAvatar\": \"none\", \"SOGoDefaultCalendar\": \"selected\", \"SOGoCalendarEventsDefaultClassification\": \"PUBLIC\", \"SOGoGravatarEnabled\": 0, \"SOGoMailComposeMessageType\": \"html\", \"SOGoLongDateFormat\": \"%A, %B %d, %Y\", \"AuxiliaryMailAccounts\": [], \"SOGoMailDisplayRemoteInlineImages\": \"never\", \"SOGoMailComposeFontSize\": 0, \"SOGoMailMessageForwarding\": \"inline\", \"SOGoDayEndTime\": \"18:00\", \"SOGoMailReplyPlacement\": \"below\", \"locale\": {\"months\": [\"Januar\", \"Februar\", \"März\", \"April\", \"Mai\", \"Juni\", \"Juli\", \"August\", \"September\", \"Oktober\", \"November\", \"Dezember\"], \"shortMonths\": [\"Jan\", \"Feb\", \"Mär\", \"Apr\", \"Mai\", \"Jun\", \"Jul\", \"Aug\", \"Sep\", \"Okt\", \"Nov\", \"Dez\"], \"shortDays\": [\"So\", \"Mo\", \"Di\", \"Mi\", \"Do\", \"Fr\", \"Sa\"], \"days\": [\"Sonntag\", \"Montag\", \"Dienstag\", \"Mittwoch\", \"Donnerstag\", \"Freitag\", \"Samstag\"]}}','{\"Contact\": {\"SortingState\": [\"c_cn\", \"1\"]}, \"Calendar\": {\"SelectedList\": \"eventsListView\", \"FoldersOrder\": [\"personal\"], \"PreventInvitationsWhitelist\": {}, \"EventsFilterState\": \"view_next7\", \"View\": \"weekview\"}, \"Mail\": {}}');
/*!40000 ALTER TABLE `sogo_user_profile` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Temporary table structure for view `sogo_view`
--

DROP TABLE IF EXISTS `sogo_view`;
/*!50001 DROP VIEW IF EXISTS `sogo_view`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE TABLE `sogo_view` (
  `c_uid` tinyint NOT NULL,
  `c_name` tinyint NOT NULL,
  `c_password` tinyint NOT NULL,
  `c_cn` tinyint NOT NULL,
  `mail` tinyint NOT NULL,
  `aliases` tinyint NOT NULL,
  `ad_aliases` tinyint NOT NULL,
  `senderacl` tinyint NOT NULL,
  `home` tinyint NOT NULL
) ENGINE=MyISAM */;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `spamalias`
--

DROP TABLE IF EXISTS `spamalias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `spamalias` (
  `address` varchar(255) NOT NULL,
  `goto` text NOT NULL,
  `validity` int(11) NOT NULL,
  PRIMARY KEY (`address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `spamalias`
--

LOCK TABLES `spamalias` WRITE;
/*!40000 ALTER TABLE `spamalias` DISABLE KEYS */;
/*!40000 ALTER TABLE `spamalias` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `userpref`
--

DROP TABLE IF EXISTS `userpref`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `userpref` (
  `object` varchar(100) NOT NULL DEFAULT '',
  `option` varchar(50) NOT NULL DEFAULT '',
  `value` varchar(100) NOT NULL DEFAULT '',
  `prefid` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`prefid`),
  KEY `object` (`object`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `userpref`
--

LOCK TABLES `userpref` WRITE;
/*!40000 ALTER TABLE `userpref` DISABLE KEYS */;
/*!40000 ALTER TABLE `userpref` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Final view structure for view `grouped_domain_alias_address`
--

/*!50001 DROP TABLE IF EXISTS `grouped_domain_alias_address`*/;
/*!50001 DROP VIEW IF EXISTS `grouped_domain_alias_address`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`mailcow`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `grouped_domain_alias_address` AS select `mailbox`.`username` AS `username`,ifnull(group_concat(`mailbox`.`local_part`,'@',`alias_domain`.`alias_domain` separator ' '),'') AS `ad_alias` from (`mailbox` left join `alias_domain` on((`alias_domain`.`target_domain` = `mailbox`.`domain`))) group by `mailbox`.`username` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `grouped_mail_aliases`
--

/*!50001 DROP TABLE IF EXISTS `grouped_mail_aliases`*/;
/*!50001 DROP VIEW IF EXISTS `grouped_mail_aliases`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`mailcow`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `grouped_mail_aliases` AS select `alias`.`goto` AS `username`,ifnull(group_concat(`alias`.`address` separator ' '),'') AS `aliases` from `alias` where ((`alias`.`address` <> `alias`.`goto`) and (`alias`.`active` = '1') and (not((`alias`.`address` like '@%')))) group by `alias`.`goto` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `grouped_sender_acl`
--

/*!50001 DROP TABLE IF EXISTS `grouped_sender_acl`*/;
/*!50001 DROP VIEW IF EXISTS `grouped_sender_acl`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`mailcow`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `grouped_sender_acl` AS select `sender_acl`.`logged_in_as` AS `username`,ifnull(group_concat(`sender_acl`.`send_as` separator ' '),'') AS `send_as` from `sender_acl` where (not((`sender_acl`.`send_as` like '@%'))) group by `sender_acl`.`logged_in_as` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `sogo_view`
--

/*!50001 DROP TABLE IF EXISTS `sogo_view`*/;
/*!50001 DROP VIEW IF EXISTS `sogo_view`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`mailcow`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `sogo_view` AS select `mailbox`.`username` AS `c_uid`,`mailbox`.`username` AS `c_name`,`mailbox`.`password` AS `c_password`,`mailbox`.`name` AS `c_cn`,`mailbox`.`username` AS `mail`,ifnull(`ga`.`aliases`,'') AS `aliases`,ifnull(`gda`.`ad_alias`,'') AS `ad_aliases`,ifnull(`gs`.`send_as`,'') AS `senderacl`,concat('/var/vmail/',`mailbox`.`maildir`) AS `home` from (((`mailbox` left join `grouped_mail_aliases` `ga` on((`ga`.`username` = `mailbox`.`username`))) left join `grouped_sender_acl` `gs` on((`gs`.`username` = `mailbox`.`username`))) left join `grouped_domain_alias_address` `gda` on((`gda`.`username` = `mailbox`.`username`))) where (`mailbox`.`active` = '1') */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2016-12-11  9:13:17
