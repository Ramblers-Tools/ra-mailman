ALTER TABLE `#__ra_mail_shots` ADD COLUMN `cancelled` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_scheduled`;
