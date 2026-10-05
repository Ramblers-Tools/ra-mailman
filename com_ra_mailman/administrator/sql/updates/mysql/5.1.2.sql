ALTER TABLE `#__ra_mail_shots` ADD COLUMN `contact_id` INT NULL AFTER `attachment`;
UPDATE `#__ra_mail_shots` AS s INNER JOIN `#__users` AS u ON u.email = s.reply_to SET s.contact_id = u.id WHERE s.contact_id IS NULL AND s.reply_to <> '';
