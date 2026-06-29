# 8 files in total
# 11/09/24 CB remove mailshot / ordering
# 19/10/24 CB add clusters/groups
# 03/02/25 CB set defaults for ra_profiles home_group & preferred_name
# 01/06/25 CB add ra_import_reports, remove clusters & groups
# 16/06/25 CB added table ra_emails
# 20/10/25 CB added mail_lists / emails_outstanding
# 30/03/26 CB mailshots: add record_type and event_id
# 29/04/26 CB add mail_lists / description
# 22/06/26 CB add mailshot / reply_to
CREATE TABLE IF NOT EXISTS `#__ra_emails` (
    `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
    `sub_system` VARCHAR(10)  NULL  DEFAULT "",
    `record_type` VARCHAR(2)  NULL  DEFAULT "",
    `ref` INT  DEFAULT "0", 
    `date_sent` VARCHAR(20)  NULL  DEFAULT "",
    `sender_name` VARCHAR(100)  NULL  DEFAULT "",
    `sender_email` VARCHAR(100)  NULL  DEFAULT "",
    `addressee_name` VARCHAR(100)  NULL  DEFAULT "",
    `addressee_email` TEXT,
    `title` VARCHAR(100)  NOT NULL ,
    `body` TEXT NOT NULL ,
    `attachments` TEXT NULL ,
`contact_id` INT(11),
`reply_to` VARCHAR(255)  NOT NULL ,
    `state` TINYINT(1)  NULL  DEFAULT 1,
    `created` DATETIME NULL  DEFAULT NULL ,
    `created_by` INT(11)  NULL  DEFAULT 0,
    `modified` DATETIME NULL  DEFAULT NULL ,
    `modified_by` INT(11)  NULL  DEFAULT 0,
    `checked_out` INT(11)  UNSIGNED,
    `checked_out_time` DATETIME NULL  DEFAULT NULL ,
    PRIMARY KEY (`id`)
    ,KEY `idx_state` (`state`)
    ,KEY `idx_checked_out` (`checked_out`)
    ,KEY `idx_created_by` (`created_by`)
    ,KEY `idx_modified_by` (`modified_by`)
) DEFAULT COLLATE=utf8mb4_unicode_ci;
# ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `#__ra_import_reports` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `date_phase1` DATETIME NOT NULL ,
            `date_completed` DATETIME NULL ,
            `method_id` int(11) NOT NULL,
            `list_id` int(11) NOT NULL,
            `user_id` int(11) NOT NULL,
            `num_records` INT  NOT NULL DEFAULT "0",
            `num_errors` INT  NOT NULL DEFAULT "0",
            `num_users` INT  NOT NULL DEFAULT "0",
            `num_subs` INT  NOT NULL DEFAULT "0",
            `num_lapsed` INT  NOT NULL DEFAULT "0",
            `ip_address` VARCHAR(255)  NULL  DEFAULT "",
            `error_report` MEDIUMTEXT  DEFAULT NULL,
            `new_users` MEDIUMTEXT DEFAULT NULL,
            `new_subs` MEDIUMTEXT DEFAULT NULL,
            `lapsed_members` TEXT DEFAULT NULL,
            `input_file` VARCHAR(255) NOT NULL,
            `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `created_by` INT NULL DEFAULT "0",
            `modified` DATETIME NULL DEFAULT NULL,
            `modified_by` INT NULL DEFAULT "0",
            `checked_out_time` DATETIME NULL  DEFAULT NULL ,
            `checked_out` INT NULL,
            `state` TINYINT(1)  NULL  DEFAULT 1,
            PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
# ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `#__ra_mail_access` (
        `id` INT NOT NULL AUTO_INCREMENT,
        `name` varchar(25) NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

INSERT INTO `#__ra_mail_access` (`name`) VALUES
    ('Subscriber'),
    ('Author'),
    ('Owner');
# ------------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `#__ra_mail_lists` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`state` INT NOT NULL,
	`name` VARCHAR(255) NOT NULL,
    `description` VARCHAR(512) DEFAULT "",
	`group_code` VARCHAR(4) NOT NULL,
    `group_primary` VARCHAR(4) DEFAULT NULL, 
	`owner_id` INT NOT NULL,
	`record_type` VARCHAR(1) NOT NULL,
	`home_group_only` INT NOT NULL,
    `chat_list` VARCHAR(1)  NOT NULL DEFAULT "0",
	`footer` MEDIUMTEXT NOT NULL,
    `emails_outstanding` INT NOT NULL DEFAULT "0",   
# Following two fields probably not required
	`ordering` INT NULL,
    `checked_out_time` DATETIME NULL DEFAULT NULL,

 	`created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,   
	`created_by` INT NULL DEFAULT "0",
 	`modified` DATETIME NULL DEFAULT NULL,
	`modified_by` INT NULL DEFAULT "0",
    PRIMARY KEY (`id`),
    INDEX idx_owner_id(owner_id),
    INDEX idx_created_by(created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
# ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `#__ra_mail_methods` (
        `id` INT NOT NULL AUTO_INCREMENT,
        `name` varchar(25) NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

INSERT INTO `#__ra_mail_methods` (`name`) VALUES
('Self registered'),
('Administrator'),
('Corporate feed'),
('MailChimp'),
('CSV'),
('Email');
# ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `#__ra_mail_recipients` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`mailshot_id` INT NOT NULL,
	`user_id` INT NOT NULL,
	`email` VARCHAR(100) NOT NULL,
	`created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `created_by` INT NULL DEFAULT "0",
	`ip_address` VARCHAR(50) NOT NULL,
    PRIMARY KEY (`id`),
    INDEX idx_user_id(user_id),
    INDEX idx_mailshot_id(mailshot_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
# ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `#__ra_mail_shots` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`record_type` VARCHAR(1) DEFAULT "M" NOT NULL,
        `mail_list_id` INT NULL,
        `event_id` INT NULL,
        `title` VARCHAR(255) NOT NULL,
        `body` longtext NOT NULL,
        `final_message` longtext,
        `attachment` VARCHAR(255) NOT NULL DEFAULT '',
        `processing_started` DATETIME DEFAULT NULL,
        `date_sent` DATETIME DEFAULT NULL,
        `reply_to` VARCHAR(255) NULL,
        `state` TINYINT NOT NULL,
 	`created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,   
	`created_by` INT NULL DEFAULT "0",
 	`modified` DATETIME NULL DEFAULT NULL,
	`modified_by`INT NULL DEFAULT "0",
    PRIMARY KEY (`id`),
    INDEX idx_mail_list_id(mail_list_id),
    INDEX idx_event_id(event_id),
    INDEX idx_created_by(created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
# ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `#__ra_mail_subscriptions` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`list_id` INT NOT NULL,
	`user_id` INT NOT NULL,
	`record_type` INT NOT NULL,
        `method_id` INT NOT NULL,
        `state` TINYINT NOT NULL,
        `ip_address` VARCHAR(50) NOT NULL,
	`created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,  
        `created_by` INT NULL DEFAULT "0",
	`modified` DATETIME NULL DEFAULT NULL,
        `modified_by` INT NULL DEFAULT "0",
	`expiry_date` DATE NULL,
        `reminder_sent` DATETIME,
    PRIMARY KEY (`id`),
    INDEX idx_user_id(user_id),
    INDEX idx_list_id(list_id),
    INDEX idx_method_id(method_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
# ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `#__ra_mail_subscriptions_audit` (
	`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`object_id` INT NOT NULL,
	`field_name` VARCHAR(50) NOT NULL,
	`old_value`VARCHAR(50) NOT NULL,
        `new_value` VARCHAR(50) NOT NULL,
	`ip_address` VARCHAR(50) NOT NULL,
        `created` TIMESTAMP DEFAULT CURRENT_TIMESTAMP, 
        `created_by` INT NULL DEFAULT "0",
    PRIMARY KEY (`id`),
    INDEX idx_object_id(object_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
# ------------------------------------------------------------------------------
# initially, only required for corporate mailman system
# may be installed by ra_tools
CREATE TABLE IF NOT EXISTS `#__ra_organisations` (
  `id` int(10) UNSIGNED NOT NULL,
  `record_type` char(1) NOT NULL DEFAULT 'A',
  `code` varchar(4) NOT NULL,
  `nation_id` INT  NULL DEFAULT 1,
  `cluster` varchar(3)  NULL,
  `name` varchar(100) NOT NULL,
  `details` mediumtext NOT NULL,
  `website` varchar(150) NOT NULL,
  `co_url` varchar(150) NOT NULL,
  `latitude` decimal(10,8) NOT NULL,
  `longitude` decimal(14,12) NOT NULL,
  `logo` varchar(50) NOT NULL,
  `logo_align` varchar(10) NOT NULL,
  `email_header` varchar(50) DEFAULT NULL,
  `colour_header` varchar(24) DEFAULT NULL,
  `colour_body` varchar(24) NOT NULL,
  `colour_footer` varchar(24) DEFAULT NULL,
  `welcome_letter` text,
  `reminder_letter` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
# ------------------------------------------------------------------------------
# Emails, Logfile and Profiles are required, but are installed by com_ra_tools
