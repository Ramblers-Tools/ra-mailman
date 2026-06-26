-- Joomla installer schema for East Cheshire Ramblers components.
-- Generated from www05--3135377212.sql on 2026-06-26.
-- Existing tables are left untouched by CREATE TABLE IF NOT EXISTS.

CREATE TABLE IF NOT EXISTS `#__ra_api_sites` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `sub_system` varchar(10) NOT NULL DEFAULT 'RA Events',
  `title` varchar(100) NOT NULL,
  `url` varchar(100) NOT NULL,
  `token` varchar(255) NOT NULL,
  `colour` varchar(25) NOT NULL,
  `state` tinyint(1) DEFAULT 1,
  `ordering` int(11) DEFAULT 0,
  `checked_out` int(11) UNSIGNED DEFAULT NULL,
  `checked_out_time` datetime DEFAULT NULL,
  `created` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT 0,
  `modified` datetime DEFAULT NULL,
  `modified_by` int(11) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__ra_areas` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `nation_id` int(11) NOT NULL DEFAULT 1,
  `code` varchar(2) NOT NULL,
  `name` varchar(100) NOT NULL,
  `bespoke` varchar(1) NOT NULL DEFAULT '0',
  `details` mediumtext NOT NULL,
  `website` varchar(150) NOT NULL,
  `co_url` varchar(150) NOT NULL,
  `cluster` varchar(3) DEFAULT NULL,
  `latitude` decimal(14,12) NOT NULL DEFAULT 0.000000000000,
  `longitude` decimal(15,13) NOT NULL DEFAULT 0.0000000000000,
  `state` int(11) DEFAULT 0,
  `created` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT 0,
  `modified` datetime DEFAULT NULL,
  `modified_by` int(11) DEFAULT 0,
  `checked_out` int(11) UNSIGNED DEFAULT NULL,
  `checked_out_time` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__ra_bookings` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `num_places` int(11) NOT NULL DEFAULT 1,
  `partner` varchar(50) DEFAULT NULL,
  `custom1` varchar(50) NOT NULL DEFAULT '?',
  `custom2` varchar(50) NOT NULL DEFAULT '?',
  `state` int(11) DEFAULT 0,
  `created` datetime NOT NULL,
  `created_by` int(11) NOT NULL,
  `confirmed` datetime DEFAULT NULL,
  `confirmed_by` int(11) NOT NULL DEFAULT 0,
  `cancelled` datetime DEFAULT NULL,
  `cancelled_by` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_event_id` (`event_id`),
  KEY `idx_userid` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__ra_clusters` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` varchar(3) NOT NULL,
  `name` varchar(20) NOT NULL,
  `contact_id` int(11) DEFAULT NULL,
  `area_list` varchar(255) DEFAULT NULL,
  `website` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__ra_control` (
  `record_type` int(11) NOT NULL,
  `key_value` varchar(255) NOT NULL,
  PRIMARY KEY (`record_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__ra_emails` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `sub_system` varchar(10) DEFAULT '',
  `record_type` varchar(2) DEFAULT '',
  `ref` int(11) DEFAULT 0,
  `date_sent` varchar(20) DEFAULT '',
  `sender_name` varchar(100) DEFAULT '',
  `sender_email` text DEFAULT '',
  `addressee_name` varchar(100) DEFAULT '',
  `addressee_email` text DEFAULT NULL,
  `title` varchar(100) NOT NULL,
  `body` text NOT NULL,
  `attachments` text DEFAULT NULL,
  `state` tinyint(1) DEFAULT 1,
  `created` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT 0,
  `modified` datetime DEFAULT NULL,
  `modified_by` int(11) DEFAULT 0,
  `checked_out` int(11) UNSIGNED DEFAULT NULL,
  `checked_out_time` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ref` (`ref`),
  KEY `idx_state` (`state`),
  KEY `idx_checked_out` (`checked_out`),
  KEY `idx_created_by` (`created_by`),
  KEY `idx_modified_by` (`modified_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__ra_events` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `event_id` int(11) DEFAULT NULL,
  `event_date` date DEFAULT NULL,
  `event_date_end` date DEFAULT NULL,
  `event_time` varchar(5) NOT NULL DEFAULT '19:00',
  `event_type_id` int(11) NOT NULL,
  `title` varchar(255) DEFAULT '',
  `details` text DEFAULT NULL,
  `reports` text DEFAULT NULL,
  `minutes` text DEFAULT NULL,
  `group_code` varchar(4) NOT NULL,
  `location` text DEFAULT NULL,
  `contact_id` int(11) DEFAULT 0,
  `url` varchar(255) DEFAULT '',
  `url_description` varchar(255) DEFAULT '',
  `attachments` varchar(255) DEFAULT '',
  `attachment_description` varchar(255) DEFAULT '',
  `emails_outstanding` int(11) DEFAULT 0,
  `publication_date` datetime DEFAULT NULL,
  `shareable` int(11) DEFAULT 0,
  `share_date` datetime DEFAULT NULL,
  `bookable` int(11) DEFAULT 0,
  `max_bookings` int(11) DEFAULT 20,
  `num_bookings` int(11) DEFAULT 0,
  `notify_organiser` int(11) DEFAULT 0,
  `booking_info` text DEFAULT NULL,
  `booking1` varchar(50) DEFAULT NULL,
  `booking1_hint` varchar(100) DEFAULT NULL,
  `booking2` varchar(50) DEFAULT NULL,
  `booking2_hint` varchar(100) DEFAULT NULL,
  `api_site_id` int(11) DEFAULT NULL,
  `original_id` int(11) DEFAULT NULL,
  `created` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT 0,
  `modified` datetime DEFAULT NULL,
  `modified_by` int(11) DEFAULT 0,
  `checked_out_time` datetime DEFAULT NULL,
  `checked_out` int(11) DEFAULT NULL,
  `state` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_event_type_id` (`event_type_id`),
  KEY `idx_api_site_id` (`api_site_id`),
  KEY `idx_original_id` (`original_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__ra_event_states` (
  `id` int(11) NOT NULL,
  `seq` int(11) NOT NULL,
  `title` varchar(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__ra_event_types` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `description` varchar(20) NOT NULL,
  `ordering` int(11) NOT NULL DEFAULT 0,
  `state` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__ra_groups` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `area_id` int(11) NOT NULL DEFAULT 1,
  `code` varchar(4) NOT NULL,
  `name` varchar(100) NOT NULL,
  `bespoke` varchar(1) NOT NULL DEFAULT '0',
  `details` mediumtext NOT NULL,
  `group_type` varchar(1) NOT NULL DEFAULT 'G',
  `website` varchar(250) DEFAULT NULL,
  `co_url` varchar(150) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `latitude` decimal(14,12) NOT NULL DEFAULT 0.000000000000,
  `longitude` decimal(14,12) NOT NULL DEFAULT 0.000000000000,
  `state` int(11) DEFAULT 0,
  `created` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT 0,
  `modified` datetime DEFAULT NULL,
  `modified_by` int(11) DEFAULT 0,
  `checked_out` int(11) UNSIGNED DEFAULT NULL,
  `checked_out_time` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__ra_import_reports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `date_phase1` datetime NOT NULL,
  `date_completed` datetime DEFAULT NULL,
  `method_id` int(11) NOT NULL,
  `list_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `num_records` int(11) NOT NULL DEFAULT 0,
  `num_errors` int(11) NOT NULL DEFAULT 0,
  `num_users` int(11) NOT NULL DEFAULT 0,
  `num_subs` int(11) NOT NULL DEFAULT 0,
  `num_lapsed` int(11) NOT NULL DEFAULT 0,
  `ip_address` varchar(255) DEFAULT '',
  `error_report` mediumtext DEFAULT NULL,
  `new_users` mediumtext DEFAULT NULL,
  `new_subs` mediumtext DEFAULT NULL,
  `lapsed_members` text DEFAULT NULL,
  `input_file` varchar(255) NOT NULL,
  `created` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT 0,
  `modified` datetime DEFAULT NULL,
  `modified_by` int(11) DEFAULT 0,
  `checked_out_time` datetime DEFAULT NULL,
  `checked_out` int(11) DEFAULT NULL,
  `state` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__ra_logfile` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `log_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `sub_system` varchar(12) DEFAULT NULL,
  `record_type` char(2) NOT NULL,
  `ref` varchar(10) DEFAULT NULL,
  `message` mediumtext CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__ra_mail_access` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(25) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__ra_mail_lists` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `state` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` varchar(512) DEFAULT '',
  `group_code` varchar(4) NOT NULL,
  `group_primary` varchar(4) DEFAULT NULL,
  `owner_id` int(11) NOT NULL,
  `record_type` varchar(1) NOT NULL,
  `home_group_only` int(11) NOT NULL,
  `chat_list` varchar(1) NOT NULL DEFAULT '0',
  `footer` mediumtext NOT NULL,
  `emails_outstanding` int(11) NOT NULL DEFAULT 0,
  `ordering` int(11) DEFAULT NULL,
  `checked_out_time` datetime DEFAULT NULL,
  `created` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT 0,
  `modified` datetime DEFAULT NULL,
  `modified_by` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_owner_id` (`owner_id`),
  KEY `idx_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__ra_mail_methods` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(25) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__ra_mail_recipients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `mailshot_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `created` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT 0,
  `ip_address` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_mailshot_id` (`mailshot_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__ra_mail_shots` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `record_type` varchar(1) DEFAULT 'M',
  `mail_list_id` int(11) DEFAULT NULL,
  `event_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `body` longtext NOT NULL,
  `final_message` longtext DEFAULT NULL,
  `attachment` varchar(255) NOT NULL DEFAULT '',
  `processing_started` datetime DEFAULT NULL,
  `date_sent` datetime DEFAULT NULL,
  `state` tinyint(4) NOT NULL,
  `created` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT 0,
  `modified` datetime DEFAULT NULL,
  `modified_by` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_mail_list_id` (`mail_list_id`),
  KEY `idx_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__ra_mail_subscriptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `list_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `record_type` int(11) NOT NULL,
  `method_id` int(11) NOT NULL,
  `state` tinyint(4) NOT NULL,
  `ip_address` varchar(50) NOT NULL,
  `created` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT 0,
  `modified` datetime DEFAULT NULL,
  `modified_by` int(11) DEFAULT 0,
  `expiry_date` date DEFAULT NULL,
  `reminder_sent` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_list_id` (`list_id`),
  KEY `idx_method_id` (`method_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__ra_mail_subscriptions_audit` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `object_id` int(11) NOT NULL,
  `field_name` varchar(50) NOT NULL,
  `old_value` varchar(50) NOT NULL,
  `new_value` varchar(50) NOT NULL,
  `ip_address` varchar(50) NOT NULL,
  `created` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_object_id` (`object_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__ra_nations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(2) NOT NULL,
  `name` varchar(100) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

CREATE TABLE IF NOT EXISTS `#__ra_profiles` (
  `member_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id` int(10) UNSIGNED DEFAULT NULL,
  `home_group` varchar(255) NOT NULL,
  `preferred_name` varchar(60) DEFAULT NULL,
  `salesforceId` varchar(10) DEFAULT NULL,
  `groupName` varchar(100) DEFAULT NULL,
  `membershipNumber` int(11) DEFAULT NULL,
  `memberType` varchar(9) DEFAULT NULL,
  `memberTerm` varchar(6) DEFAULT NULL,
  `memberStatus` varchar(15) DEFAULT NULL,
  `type` varchar(10) DEFAULT NULL,
  `jointWith` varchar(10) DEFAULT NULL,
  `title` varchar(6) DEFAULT NULL,
  `initials` varchar(5) DEFAULT NULL,
  `firstName` varchar(100) DEFAULT NULL,
  `lastName` varchar(100) DEFAULT NULL,
  `address1` varchar(100) DEFAULT NULL,
  `address2` varchar(100) DEFAULT NULL,
  `address3` varchar(100) DEFAULT NULL,
  `town` varchar(15) DEFAULT NULL,
  `county` varchar(14) DEFAULT NULL,
  `country` varchar(14) DEFAULT NULL,
  `postcode` varchar(8) DEFAULT NULL,
  `email` varchar(38) DEFAULT NULL,
  `landlineTelephone` varchar(20) DEFAULT NULL,
  `mobileNumber` varchar(20) DEFAULT NULL,
  `membershipExpiryDate` date DEFAULT NULL,
  `ramblersJoinDate` date DEFAULT NULL,
  `areaName` varchar(100) DEFAULT NULL,
  `areaJoinedDate` date DEFAULT NULL,
  `groupCode` varchar(4) DEFAULT NULL,
  `groupJoinedDate` date DEFAULT NULL,
  `volunteer` char(1) DEFAULT NULL,
  `emailMarketingConsent` char(1) DEFAULT NULL,
  `areaMarketingConsent` char(1) DEFAULT NULL,
  `groupMarketingConsent` char(1) DEFAULT NULL,
  `otherMarketingConsent` char(1) DEFAULT NULL,
  `emailPermissionLastUpdated` date DEFAULT NULL,
  `postDirectMarketing` char(1) DEFAULT NULL,
  `postPermissionLastUpdated` date DEFAULT NULL,
  `telephoneDirectMarketing` char(1) DEFAULT NULL,
  `telephonePermissionLastUpdated` date DEFAULT NULL,
  `walkProgrammeOptOut` char(1) DEFAULT NULL,
  `affiliateMemberPrimaryGroup` varchar(36) DEFAULT NULL,
  `sortName` varchar(11) DEFAULT NULL,
  `original` int(11) DEFAULT NULL,
  `state` tinyint(1) DEFAULT 1,
  `created` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT 0,
  `modified` datetime DEFAULT NULL,
  `modified_by` int(11) DEFAULT 0,
  `checked_out` int(11) DEFAULT NULL,
  PRIMARY KEY (`member_id`),
  KEY `idx_ra_profiles_id` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__ra_profiles_audit` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `date_amended` datetime NOT NULL DEFAULT current_timestamp(),
  `object_id` int(11) NOT NULL,
  `field_name` varchar(50) NOT NULL DEFAULT '',
  `record_type` char(1) NOT NULL DEFAULT '',
  `field_value` longtext DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `object_id` (`object_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__ra_walks_editor_contacts` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `asset_id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `ordering` int(11) DEFAULT 0,
  `state` tinyint(1) DEFAULT 1,
  `checked_out` int(11) DEFAULT NULL,
  `checked_out_time` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT 0,
  `modified_by` int(11) DEFAULT 0,
  `contactname` varchar(255) DEFAULT '',
  `displayname` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT '',
  `telephone1` varchar(255) DEFAULT '',
  `telephone2` varchar(255) DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `idx_state` (`state`),
  KEY `idx_checked_out` (`checked_out`),
  KEY `idx_created_by` (`created_by`),
  KEY `idx_modified_by` (`modified_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__ra_walks_editor_grades` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `checked_out` int(11) UNSIGNED DEFAULT NULL,
  `checked_out_time` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT 0,
  `modified_by` int(11) DEFAULT 0,
  `ordering` int(11) DEFAULT 0,
  `state` tinyint(1) DEFAULT 1,
  `localgrade` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_checked_out` (`checked_out`),
  KEY `idx_created_by` (`created_by`),
  KEY `idx_modified_by` (`modified_by`),
  KEY `idx_state` (`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__ra_walks_editor_places` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `ordering` int(11) DEFAULT 0,
  `state` tinyint(1) DEFAULT 1,
  `checked_out` int(11) DEFAULT NULL,
  `checked_out_time` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT 0,
  `modified_by` int(11) DEFAULT 0,
  `name` varchar(255) NOT NULL,
  `abbr` varchar(255) DEFAULT '',
  `postcode` varchar(255) DEFAULT '',
  `latitude` double DEFAULT NULL,
  `longitude` double DEFAULT NULL,
  `gridreference` varchar(255) DEFAULT '',
  `what3words` varchar(255) DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `idx_state` (`state`),
  KEY `idx_checked_out` (`checked_out`),
  KEY `idx_created_by` (`created_by`),
  KEY `idx_modified_by` (`modified_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__ra_walks_editor_walks` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `asset_id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `ordering` int(11) DEFAULT 0,
  `state` tinyint(1) DEFAULT 1,
  `checked_out` int(11) DEFAULT NULL,
  `checked_out_time` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT 0,
  `modified_by` int(11) DEFAULT 0,
  `date` datetime DEFAULT NULL,
  `category` text DEFAULT NULL,
  `content` mediumtext DEFAULT '',
  `status` varchar(255) NOT NULL DEFAULT 'Draft',
  PRIMARY KEY (`id`),
  KEY `idx_state` (`state`),
  KEY `idx_checked_out` (`checked_out`),
  KEY `idx_created_by` (`created_by`),
  KEY `idx_modified_by` (`modified_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The following requested tables were not present in the source export, so no structure could be generated:
-- #__ra_organisations
-- #__ra_walks
-- #__ra_walks_follow
