CREATE TABLE IF NOT EXISTS `admin` (
	`username` VARCHAR(255) NOT NULL,
	`password` VARCHAR(255) NOT NULL,
	`superadmin` TINYINT(1) NOT NULL DEFAULT '0',
	`created` DATETIME NOT NULL DEFAULT '2016-01-01 00:00:00',
	`modified` DATETIME NOT NULL DEFAULT '2016-01-01 00:00:00',
	`active` TINYINT(1) NOT NULL DEFAULT '1',
	PRIMARY KEY (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `alias` (
	`address` VARCHAR(255) NOT NULL,
	`goto` TEXT NOT NULL,
	`domain` VARCHAR(255) NOT NULL,
	`created` DATETIME NOT NULL DEFAULT '2016-01-01 00:00:00',
	`modified` DATETIME NOT NULL DEFAULT '2016-01-01 00:00:00',
	`active` TINYINT(1) NOT NULL DEFAULT '1',
	PRIMARY KEY (`address`),
	KEY `domain` (`domain`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `sender_acl` (
	`logged_in_as` VARCHAR(255) NOT NULL,
	`send_as` VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `spamalias` (
	`address` VARCHAR(255) NOT NULL,
	`goto` TEXT NOT NULL,
	`validity` INT(11) NOT NULL,
	PRIMARY KEY (`address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `alias_domain` (
	`alias_domain` VARCHAR(255) NOT NULL,
	`target_domain` VARCHAR(255) NOT NULL,
	`created` DATETIME NOT NULL DEFAULT '2016-01-01 00:00:00',
	`modified` DATETIME NOT NULL DEFAULT '2016-01-01 00:00:00',
	`active` TINYINT(1) NOT NULL DEFAULT '1',
	PRIMARY KEY (`alias_domain`),
	KEY `active` (`active`),
	KEY `target_domain` (`target_domain`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `domain` (
	`domain` VARCHAR(255) NOT NULL,
	`description` VARCHAR(255),
	`aliases` INT(10) NOT NULL DEFAULT '0',
	`mailboxes` INT(10) NOT NULL DEFAULT '0',
	`maxquota` BIGINT(20) NOT NULL DEFAULT '0',
	`quota` BIGINT(20) NOT NULL DEFAULT '0',
	`transport` VARCHAR(255) NOT NULL,
	`backupmx` TINYINT(1) NOT NULL DEFAULT '0',
	`relay_all_recipients` TINYINT(1) NOT NULL DEFAULT '0',
	`created` DATETIME NOT NULL DEFAULT '2016-01-01 00:00:00',
	`modified` DATETIME NOT NULL DEFAULT '2016-01-01 00:00:00',
	`active` TINYINT(1) NOT NULL DEFAULT '1',
	PRIMARY KEY (`domain`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `domain_admins` (
	`username` VARCHAR(255) NOT NULL,
	`domain` VARCHAR(255) NOT NULL,
	`created` DATETIME NOT NULL DEFAULT '2016-01-01 00:00:00',
	`active` TINYINT(1) NOT NULL DEFAULT '1',
	KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `mailbox` (
	`username` VARCHAR(255) NOT NULL,
	`password` VARCHAR(255) NOT NULL,
	`name` VARCHAR(255),
	`maildir` VARCHAR(255) NOT NULL,
	`quota` BIGINT(20) NOT NULL DEFAULT '0',
	`local_part` VARCHAR(255) NOT NULL,
	`domain` VARCHAR(255) NOT NULL,
	`created` DATETIME NOT NULL DEFAULT '2016-01-01 00:00:00',
	`modified` DATETIME NOT NULL DEFAULT '2016-01-01 00:00:00',
	`tls_enforce_in` TINYINT(1) NOT NULL DEFAULT '0',
	`tls_enforce_out` TINYINT(1) NOT NULL DEFAULT '0',
	`kind` VARCHAR(100) NOT NULL DEFAULT '',
	`multiple_bookings` TINYINT(1) NOT NULL DEFAULT '0',
	`wants_tagged_subject` TINYINT(1) NOT NULL DEFAULT '0',
	`active` TINYINT(1) NOT NULL DEFAULT '1',
	PRIMARY KEY (`username`),
	KEY `domain` (`domain`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `quota2` (
	`username` VARCHAR(100) NOT NULL,
	`bytes` BIGINT(20) NOT NULL DEFAULT '0',
	`messages` INT(11) NOT NULL DEFAULT '0',
	PRIMARY KEY (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `filterconf` (
	`object` VARCHAR(100) NOT NULL DEFAULT '',
	`option` VARCHAR(50) NOT NULL DEFAULT '',
	`value` VARCHAR(100) NOT NULL DEFAULT '',
	`prefid` INT(11) NOT NULL AUTO_INCREMENT,
	PRIMARY KEY (`prefid`),
	KEY `object` (`object`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `imapsync` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `user2` VARCHAR(255) NOT NULL,
  `host1` VARCHAR(255) NOT NULL,
  `authmech1` ENUM('PLAIN','LOGIN','CRAM-MD5') DEFAULT 'PLAIN',
  `regextrans2` VARCHAR(255) DEFAULT '',
  `authmd51` TINYINT(1) NOT NULL DEFAULT 0,
  `domain2` VARCHAR(255) NOT NULL DEFAULT '',
  `subfolder2` VARCHAR(255) NOT NULL DEFAULT '',
  `user1` VARCHAR(255) NOT NULL,
  `password1` VARCHAR(255) NOT NULL,
  `exclude` VARCHAR(500) NOT NULL DEFAULT '',
  `maxage` SMALLINT NOT NULL DEFAULT '0',
  `mins_interval` VARCHAR(50) NOT NULL,
  `port1` SMALLINT NOT NULL,
  `enc1` ENUM('TLS','SSL','PLAIN') DEFAULT 'TLS',
  `delete2duplicates` TINYINT(1) NOT NULL DEFAULT '1',
  `returned_text` TEXT,
  `last_run` TIMESTAMP NULL DEFAULT NULL,
  `created` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `active` TINYINT(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `tfa` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(255) NOT NULL,
  `authmech` ENUM('yubi_otp', 'u2f', 'hotp', 'totp'),
  `secret` VARCHAR(255) DEFAULT NULL,
  `keyHandle` VARCHAR(255) DEFAULT NULL,
  `publicKey` VARCHAR(255) DEFAULT NULL,
  `counter` INT NOT NULL DEFAULT '0',
  `certificate` TEXT,
  `active` TINYINT(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC;

DROP VIEW IF EXISTS grouped_mail_aliases;
DROP VIEW IF EXISTS grouped_sender_acl;
DROP VIEW IF EXISTS grouped_domain_alias_address;

CREATE VIEW grouped_mail_aliases (username, aliases) AS
SELECT goto, IFNULL(GROUP_CONCAT(address SEPARATOR ' '), '') AS address FROM alias
WHERE address!=goto
AND active = '1'
AND address NOT LIKE '@%'
GROUP BY goto;

CREATE VIEW grouped_sender_acl (username, send_as) AS
SELECT logged_in_as, IFNULL(GROUP_CONCAT(send_as SEPARATOR ' '), '') AS send_as FROM sender_acl
WHERE send_as NOT LIKE '@%'
GROUP BY logged_in_as;

CREATE VIEW grouped_domain_alias_address (username, ad_alias) AS
SELECT username, IFNULL(GROUP_CONCAT(local_part, '@', alias_domain SEPARATOR ' '), '') AS ad_alias FROM mailbox
LEFT OUTER JOIN alias_domain on target_domain=domain GROUP BY username;

CREATE TABLE IF NOT EXISTS sogo_acl (
	c_folder_id INTEGER NOT NULL,
	c_object character varying(255) NOT NULL,
	c_uid character varying(255) NOT NULL,
	c_role character varying(80) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS sogo_alarms_folder (
	c_path          VARCHAR(255) NOT NULL,
	c_name          VARCHAR(255) NOT NULL,
	c_uid           VARCHAR(255) NOT NULL,
	c_recurrence_id INT(11)      DEFAULT NULL,
	c_alarm_number  INT(11)      NOT NULL,
	c_alarm_date    INT(11)      NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS sogo_cache_folder (
	c_uid          VARCHAR(255) NOT NULL,
	c_path         VARCHAR(255) NOT NULL,
	c_parent_path  VARCHAR(255) DEFAULT NULL,
	c_type         TINYINT(3)   unsigned NOT NULL,
	c_creationdate INT(11)      NOT NULL,
	c_lastmodified INT(11)      NOT NULL,
	c_version      INT(11)      NOT NULL DEFAULT '0',
	c_deleted      TINYINT(4)   NOT NULL DEFAULT '0',
	c_content      longTEXT,
	PRIMARY KEY (c_uid,c_path)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS sogo_folder_info (
	c_folder_id      BIGINT(20)    unsigned NOT NULL AUTO_INCREMENT,
	c_path           VARCHAR(255)  NOT NULL,
	c_path1          VARCHAR(255)  NOT NULL,
	c_path2          VARCHAR(255)  DEFAULT NULL,
	c_path3          VARCHAR(255)  DEFAULT NULL,
	c_path4          VARCHAR(255)  DEFAULT NULL,
	c_foldername     VARCHAR(255)  NOT NULL,
	c_location       INTeger NULL,
	c_quick_location VARCHAR(2048) DEFAULT NULL,
	c_acl_location   VARCHAR(2048) DEFAULT NULL,
	c_folder_type    VARCHAR(255)  NOT NULL,
	PRIMARY KEY (c_path),
	UNIQUE KEY c_folder_id (c_folder_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS sogo_quick_appointment (
	c_folder_id INTeger NOT NULL,
	c_name character varying(255) NOT NULL,
	c_uid character varying(255) NOT NULL,
	c_startdate INTeger,
	c_enddate INTeger,
	c_cycleenddate INTeger,
	c_title character varying(1000) NOT NULL,
	c_participants TEXT,
	c_isallday INTeger,
	c_iscycle INTeger,
	c_cycleinfo TEXT,
	c_classification INTeger NOT NULL,
	c_isopaque INTeger NOT NULL,
	c_status INTeger NOT NULL,
	c_priority INTeger,
	c_location character varying(255),
	c_orgmail character varying(255),
	c_partmails TEXT,
	c_partstates TEXT,
	c_category character varying(255),
	c_sequence INTeger,
	c_component character varying(10) NOT NULL,
	c_nextalarm INTeger,
	c_description TEXT,
	CONSTRAINT sogo_quick_appointment_pkey PRIMARY KEY (c_folder_id, c_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS sogo_quick_contact (
	c_folder_id INTeger NOT NULL,
	c_name character varying(255) NOT NULL,
	c_givenname character varying(255),
	c_cn character varying(255),
	c_sn character varying(255),
	c_screenname character varying(255),
	c_l character varying(255),
	c_mail character varying(255),
	c_o character varying(255),
	c_ou character varying(255),
	c_telephonenumber character varying(255),
	c_categories character varying(255),
	c_component character varying(10) NOT NULL,
	CONSTRAINT sogo_quick_contact_pkey PRIMARY KEY (c_folder_id, c_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS sogo_sessions_folder (
	c_id           VARCHAR(255) NOT NULL,
	c_value        VARCHAR(255) NOT NULL,
	c_creationdate INT(11)      NOT NULL,
	c_lastseen     INT(11)      NOT NULL,
	PRIMARY KEY (c_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS sogo_store (
	c_folder_id INTeger NOT NULL,
	c_name character varying(255) NOT NULL,
	c_content mediumTEXT NOT NULL,
	c_creationdate INTeger NOT NULL,
	c_lastmodified INTeger NOT NULL,
	c_version INTeger NOT NULL,
	c_deleted INTeger,
	CONSTRAINT sogo_store_pkey PRIMARY KEY (c_folder_id, c_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS sogo_user_profile (
	c_uid      VARCHAR(255) NOT NULL,
	c_defaults TEXT,
	c_settings TEXT,
	PRIMARY KEY (c_uid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC;

INSERT INTO `admin` (username, password, superadmin, created, modified, active) SELECT 'admin', '{SSHA256}K8eVJ6YsZbQCfuJvSUbaQRLr0HPLz5rC9IAp0PAFl0tmNDBkMDc0NDAyOTAxN2Rk', 1, NOW(), NOW(), 1 WHERE NOT EXISTS (SELECT * FROM `admin`);
DELETE FROM `domain_admins`;
INSERT INTO `domain_admins` (username, domain, created, active) SELECT `username`, 'ALL', NOW(), 1 FROM `admin` WHERE superadmin='1' AND `username` NOT IN (SELECT `username` FROM `domain_admins`);
