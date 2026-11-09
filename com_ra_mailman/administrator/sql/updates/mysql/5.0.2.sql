ALTER TABLE `#__ra_mail_shots` ADD COLUMN `is_scheduled` TINYINT(1) NOT NULL DEFAULT 0 AFTER `send_after`;
