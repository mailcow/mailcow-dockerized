-- Updates from version 0.1-stable

TRUNCATE TABLE `messages`;

ALTER TABLE `messages`
  DROP INDEX `idx`,
  DROP INDEX `uid`;

ALTER TABLE `cache`
  DROP INDEX `cache_key`,
  DROP INDEX `session_id`,
  ADD INDEX `user_cache_index` (`user_id`,`cache_key`);

ALTER TABLE `users`
    ADD INDEX `username_index` (`username`),
    ADD INDEX `alias_index` (`alias`);
