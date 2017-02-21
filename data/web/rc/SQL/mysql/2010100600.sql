-- Updates from version 0.4.2

ALTER TABLE `users` DROP INDEX `username_index`;
ALTER TABLE `users` ADD UNIQUE `username` (`username`, `mail_host`);

ALTER TABLE `contacts` MODIFY `email` varchar(255) NOT NULL;

TRUNCATE TABLE `messages`;
