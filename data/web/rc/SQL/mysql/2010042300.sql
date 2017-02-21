-- Updates from version 0.4-beta

ALTER TABLE `users` CHANGE `last_login` `last_login` datetime DEFAULT NULL;
UPDATE `users` SET `last_login` = NULL WHERE `last_login` = '1000-01-01 00:00:00';
