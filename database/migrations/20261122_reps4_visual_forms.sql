-- REPS 4.0 standalone CMS forms and submissions.
-- Additive only; existing request and registration workflows are not changed.
CREATE TABLE IF NOT EXISTS `site_forms` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `form_key` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `draft_json` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `published_json` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('draft','published') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `created_by` int DEFAULT NULL,
  `updated_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_site_forms_form_key` (`form_key`),
  KEY `idx_site_forms_status` (`status`),
  KEY `idx_site_forms_created_by` (`created_by`),
  KEY `idx_site_forms_updated_by` (`updated_by`),
  CONSTRAINT `fk_site_forms_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_site_forms_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `site_form_submissions` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `form_id` int UNSIGNED NOT NULL,
  `data_json` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `session_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `submitted_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_site_form_submissions_form_date` (`form_id`, `submitted_at`),
  KEY `idx_site_form_submissions_session_date` (`session_hash`, `submitted_at`),
  CONSTRAINT `fk_site_form_submissions_form` FOREIGN KEY (`form_id`) REFERENCES `site_forms` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
