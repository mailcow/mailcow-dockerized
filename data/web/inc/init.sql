CREATE TABLE IF NOT EXISTS `admin` (
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `superadmin` tinyint(1) NOT NULL DEFAULT '0',
  `created` datetime NOT NULL DEFAULT '2016-01-01 00:00:00',
  `modified` datetime NOT NULL DEFAULT '2016-01-01 00:00:00',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `alias` (
  `address` varchar(255) NOT NULL,
  `goto` text NOT NULL,
  `domain` varchar(255) NOT NULL,
  `created` datetime NOT NULL DEFAULT '2016-01-01 00:00:00',
  `modified` datetime NOT NULL DEFAULT '2016-01-01 00:00:00',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`address`),
  KEY `domain` (`domain`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `sender_acl` (
  `logged_in_as` varchar(255) NOT NULL,
  `send_as` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `spamalias` (
  `address` varchar(255) NOT NULL,
  `goto` text NOT NULL,
  `validity` int(11) NOT NULL,
  PRIMARY KEY (`address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `alias_domain` (
  `alias_domain` varchar(255) NOT NULL,
  `target_domain` varchar(255) NOT NULL,
  `created` datetime NOT NULL DEFAULT '2016-01-01 00:00:00',
  `modified` datetime NOT NULL DEFAULT '2016-01-01 00:00:00',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`alias_domain`),
  KEY `active` (`active`),
  KEY `target_domain` (`target_domain`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `domain` (
  `domain` varchar(255) NOT NULL,
  `description` varchar(255),
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

CREATE TABLE IF NOT EXISTS `domain_admins` (
  `username` varchar(255) NOT NULL,
  `domain` varchar(255) NOT NULL,
  `created` datetime NOT NULL DEFAULT '2016-01-01 00:00:00',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `mailbox` (
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `name` varchar(255),
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

CREATE TABLE IF NOT EXISTS `quota2` (
  `username` varchar(100) NOT NULL,
  `bytes` bigint(20) NOT NULL DEFAULT '0',
  `messages` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `filterconf` (
  `object` varchar(100) NOT NULL DEFAULT '',
  `option` varchar(50) NOT NULL DEFAULT '',
  `value` varchar(100) NOT NULL DEFAULT '',
  `prefid` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`prefid`),
  KEY `object` (`object`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC;

DROP VIEW IF EXISTS grouped_mail_aliases;
DROP VIEW IF EXISTS grouped_sender_acl;
DROP VIEW IF EXISTS grouped_domain_alias_address;
DROP VIEW IF EXISTS sogo_view;

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

CREATE VIEW sogo_view (c_uid, c_name, c_password, c_cn, mail, aliases, ad_aliases, senderacl, home) AS
SELECT mailbox.username, mailbox.username, mailbox.password, mailbox.name, mailbox.username, IFNULL(ga.aliases, ''), IFNULL(gda.ad_alias, ''), IFNULL(gs.send_as, ''), CONCAT('/var/vmail/', maildir)
FROM mailbox
LEFT OUTER JOIN grouped_mail_aliases ga ON ga.username = mailbox.username
LEFT OUTER JOIN grouped_sender_acl gs ON gs.username = mailbox.username
LEFT OUTER JOIN grouped_domain_alias_address gda ON gda.username = mailbox.username
WHERE mailbox.active = '1';

CREATE TABLE IF NOT EXISTS sogo_acl (
	c_folder_id int(11)      NOT NULL,
	c_object    varchar(255) NOT NULL,
	c_uid       varchar(255) NOT NULL,
	c_role      varchar(80)  NOT NULL,
	KEY sogo_acl_c_folder_id_idx (c_folder_id),
	KEY sogo_acl_c_uid_idx (c_uid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS sogo_alarms_folder (
	c_path          varchar(255) NOT NULL,
	c_name          varchar(255) NOT NULL,
	c_uid           varchar(255) NOT NULL,
	c_recurrence_id int(11)      DEFAULT NULL,
	c_alarm_number  int(11)      NOT NULL,
	c_alarm_date    int(11)      NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS sogo_cache_folder (
	c_uid          varchar(255) NOT NULL,
	c_path         varchar(255) NOT NULL,
	c_parent_path  varchar(255) DEFAULT NULL,
	c_type         tinyint(3)   unsigned NOT NULL,
	c_creationdate int(11)      NOT NULL,
	c_lastmodified int(11)      NOT NULL,
	c_version      int(11)      NOT NULL DEFAULT '0',
	c_deleted      tinyint(4)   NOT NULL DEFAULT '0',
	c_content      longtext,
	PRIMARY KEY (c_uid,c_path)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS sogo_folder_info (
	c_folder_id      bigint(20)    unsigned NOT NULL AUTO_INCREMENT,
	c_path           varchar(255)  NOT NULL,
	c_path1          varchar(255)  NOT NULL,
	c_path2          varchar(255)  DEFAULT NULL,
	c_path3          varchar(255)  DEFAULT NULL,
	c_path4          varchar(255)  DEFAULT NULL,
	c_foldername     varchar(255)  NOT NULL,
	c_location       varchar(2048) DEFAULT NULL,
	c_quick_location varchar(2048) DEFAULT NULL,
	c_acl_location   varchar(2048) DEFAULT NULL,
	c_folder_type    varchar(255)  NOT NULL,
	PRIMARY KEY (c_path),
	UNIQUE KEY c_folder_id (c_folder_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS sogo_quick_appointment (
	c_folder_id      int(11)       NOT NULL,
	c_name           varchar(255)  NOT NULL,
	c_uid            varchar(255)  NOT NULL,
	c_startdate      int(11)       DEFAULT NULL,
	c_enddate        int(11)       DEFAULT NULL,
	c_cycleenddate   int(11)       DEFAULT NULL,
	c_title          varchar(1000) NOT NULL,
	c_participants   text,
	c_isallday       int(11)       DEFAULT NULL,
	c_iscycle        int(11)       DEFAULT NULL,
	c_cycleinfo      text,
	c_classification int(11)       NOT NULL,
	c_isopaque       int(11)       NOT NULL,
	c_status         int(11)       NOT NULL,
	c_priority       int(11)       DEFAULT NULL,
	c_location       varchar(255)  DEFAULT NULL,
	c_orgmail        varchar(255)  DEFAULT NULL,
	c_partmails      text,
	c_partstates     text,
	c_category       varchar(255)  DEFAULT NULL,
	c_sequence       int(11)       DEFAULT NULL,
	c_component      varchar(10)   NOT NULL,
	c_nextalarm      int(11)       DEFAULT NULL,
	c_description    text,
	PRIMARY KEY (c_folder_id,c_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS sogo_quick_contact (
	c_folder_id       int(11)      NOT NULL,
	c_name            varchar(255) NOT NULL,
	c_givenname       varchar(255) DEFAULT NULL,
	c_cn              varchar(255) DEFAULT NULL,
	c_sn              varchar(255) DEFAULT NULL,
	c_screenname      varchar(255) DEFAULT NULL,
	c_l               varchar(255) DEFAULT NULL,
	c_mail            varchar(255) DEFAULT NULL,
	c_o               varchar(255) DEFAULT NULL,
	c_ou              varchar(255) DEFAULT NULL,
	c_telephonenumber varchar(255) DEFAULT NULL,
	c_categories      varchar(255) DEFAULT NULL,
	c_component       varchar(10)  NOT NULL,
	PRIMARY KEY (c_folder_id,c_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS sogo_sessions_folder (
	c_id           varchar(255) NOT NULL,
	c_value        varchar(255) NOT NULL,
	c_creationdate int(11)      NOT NULL,
	c_lastseen     int(11)      NOT NULL,
	PRIMARY KEY (c_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS sogo_store (
	c_folder_id    int(11)      NOT NULL,
	c_name         varchar(255) NOT NULL DEFAULT '',
	c_content      mediumtext   NOT NULL,
	c_creationdate int(11)      NOT NULL,
	c_lastmodified int(11)      NOT NULL,
	c_version      int(11)      NOT NULL,
	c_deleted      int(11)      DEFAULT NULL,
	PRIMARY KEY (c_folder_id,c_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS sogo_user_profile (
	c_uid      varchar(255) NOT NULL,
	c_defaults text,
	c_settings text,
	PRIMARY KEY (c_uid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC;

INSERT INTO admin (username, password, superadmin, created, modified, active)
	SELECT 'admin', '{SSHA256}K8eVJ6YsZbQCfuJvSUbaQRLr0HPLz5rC9IAp0PAFl0tmNDBkMDc0NDAyOTAxN2Rk', 1, NOW(), NOW(), 1
	WHERE NOT EXISTS (SELECT username FROM admin WHERE superadmin='1');

INSERT INTO domain_admins (username, domain, created, active)
    SELECT 'admin', 'ALL', NOW(), 1
    WHERE NOT EXISTS (SELECT username FROM domain_admins WHERE domain='ALL');

