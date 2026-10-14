ALTER TABLE `#__ra_mail_shots` ADD COLUMN `reply_to` VARCHAR(255) NULL AFTER `date_sent` /** CAN FAIL **/;
ALTER TABLE `#__ra_mail_shots` ADD COLUMN `contact_id` INT NULL AFTER `attachment` /** CAN FAIL **/;
UPDATE `#__ra_mail_shots` AS s INNER JOIN `#__users` AS u ON u.email = s.reply_to SET s.contact_id = u.id WHERE s.contact_id IS NULL AND s.reply_to <> '';
