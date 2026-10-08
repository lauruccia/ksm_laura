-- Statistiche approfondite (obiettivi, regione/citta', ricerche, visitatori di ritorno): MySQL 8.
-- Serve solo se la migrazione non parte da sola: il deploy da cPanel lancia gia'
-- "php artisan migrate --force". Va eseguito DOPO docs/statistiche-visite.sql.

ALTER TABLE `page_views`
  ADD COLUMN `search` varchar(100) DEFAULT NULL AFTER `path`,
  ADD COLUMN `vid` char(40) DEFAULT NULL AFTER `visitor`,
  ADD COLUMN `is_returning` tinyint(1) DEFAULT NULL AFTER `is_entry`,
  ADD COLUMN `keyword` varchar(100) DEFAULT NULL AFTER `campaign`,
  ADD COLUMN `region` varchar(60) DEFAULT NULL AFTER `country`,
  ADD COLUMN `city` varchar(80) DEFAULT NULL AFTER `region`,
  ADD KEY `page_views_vid_index` (`vid`);

CREATE TABLE `conversions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `host` varchar(190) NOT NULL,
  `session_id` varchar(26) DEFAULT NULL,
  `visitor` varchar(40) DEFAULT NULL,
  `goal` varchar(12) NOT NULL,
  `value` decimal(12,2) DEFAULT NULL,
  `ref` varchar(40) DEFAULT NULL,
  `channel` varchar(12) DEFAULT NULL,
  `source` varchar(120) DEFAULT NULL,
  `campaign` varchar(120) DEFAULT NULL,
  `country` char(2) DEFAULT NULL,
  `day` date NOT NULL,
  `hour` tinyint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `conversions_host_day_index` (`host`,`day`),
  KEY `conversions_goal_day_index` (`goal`,`day`),
  KEY `conversions_session_id_index` (`session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_10_08_000100_deepen_analytics', COALESCE(MAX(`batch`), 0) + 1 FROM `migrations`;
