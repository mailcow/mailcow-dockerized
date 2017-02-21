-- Updates from version 0.2-alpha

ALTER TABLE `messages`
    ADD INDEX `created_index` (`created`);
