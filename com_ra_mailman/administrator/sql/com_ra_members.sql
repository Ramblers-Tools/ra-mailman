# ------------------------------------------------------------------------------
CREATE TABLE `#__ra_organisations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `record_type` varchar(1) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `code` varchar(4) NOT NULL,
  `nation_id` int DEFAULT NULL,
  `cluster` varchar(3) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `details` mediumtext CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `website` varchar(150) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `co_url` varchar(150) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `latitude` decimal(14,12) NOT NULL,
  `longitude` decimal(14,12) NOT NULL,
  `logo` varchar(100) DEFAULT NULL,
  `logo_align` varchar(5) NOT NULL DEFAULT 'right',
  `email_header` varchar(50) DEFAULT NULL,
  `colour_header` varchar(24) DEFAULT NULL,
  `colour_body` varchar(24) NOT NULL,
  `colour_footer` varchar(24) DEFAULT NULL,
  `welcome_letter` text,
  `reminder_letter` text,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
# ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `#__ra_profiles_audit` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `date_amended` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `object_id` INT NOT NULL,
  `field_name` varchar(50) NOT NULL DEFAULT '',
  `record_type` char(1) NOT NULL DEFAULT '',
  `field_value` longtext,
  PRIMARY KEY (`id`),
  KEY `object_id` (`object_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
# ------------------------------------------------------------------------------
