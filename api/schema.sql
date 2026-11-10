-- FixMyHyderabad — database schema
-- Import this in phpMyAdmin, or open api/install.php?run=1 in the browser.

CREATE DATABASE IF NOT EXISTS `fixmyhyderabad`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `fixmyhyderabad`;

CREATE TABLE IF NOT EXISTS `reports` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `public_id` VARCHAR(20) NOT NULL,
  `category` VARCHAR(32) NOT NULL,
  `area` VARCHAR(64) NOT NULL,
  `landmark` VARCHAR(160) NOT NULL,
  `title` VARCHAR(120) NOT NULL,
  `details` TEXT NOT NULL,
  `photo` VARCHAR(255) DEFAULT NULL,
  `reporter_name` VARCHAR(80) DEFAULT NULL,
  `contact` VARCHAR(120) DEFAULT NULL,
  `status` ENUM('Reported','Verified','Progress','Resolved') NOT NULL DEFAULT 'Reported',
  `ip_hash` CHAR(64) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_public_id` (`public_id`),
  KEY `idx_status` (`status`),
  KEY `idx_area` (`area`),
  KEY `idx_category` (`category`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
