-- Updates from version 0.1.1

ALTER TABLE `identities`
    MODIFY `signature` text, 
    MODIFY `bcc` varchar(128) NOT NULL DEFAULT '', 
    MODIFY `reply-to` varchar(128) NOT NULL DEFAULT '', 
    MODIFY `organization` varchar(128) NOT NULL DEFAULT '',
    MODIFY `name` varchar(128) NOT NULL, 
    MODIFY `email` varchar(128) NOT NULL; 
