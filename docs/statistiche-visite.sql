-- Statistiche visite: tabella page_views (MySQL 8).
-- Serve solo se la migrazione non parte da sola: il deploy da cPanel lancia gia'
-- "php artisan migrate --force". Con questo script la migrazione risulta eseguita
-- e il deploy successivo non prova a ricrearla.

CREATE TABLE `page_views` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uid` varchar(26) NOT NULL,
  `host` varchar(190) NOT NULL,
  `site_type` varchar(10) NOT NULL DEFAULT 'platform',
  `site_id` bigint unsigned DEFAULT NULL,
  `path` varchar(255) NOT NULL,
  `session_id` varchar(26) NOT NULL,
  `visitor` varchar(40) NOT NULL,
  `is_entry` tinyint(1) NOT NULL DEFAULT '0',
  `channel` varchar(12) DEFAULT NULL,
  `source` varchar(120) DEFAULT NULL,
  `medium` varchar(60) DEFAULT NULL,
  `campaign` varchar(120) DEFAULT NULL,
  `country` char(2) DEFAULT NULL,
  `device` varchar(10) NOT NULL,
  `browser` varchar(30) NOT NULL,
  `os` varchar(30) NOT NULL,
  `duration` smallint unsigned NOT NULL DEFAULT '0',
  `day` date NOT NULL,
  `hour` tinyint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `page_views_uid_unique` (`uid`),
  KEY `page_views_host_day_index` (`host`,`day`),
  KEY `page_views_day_index` (`day`),
  KEY `page_views_session_id_index` (`session_id`),
  KEY `page_views_visitor_created_at_index` (`visitor`,`created_at`),
  KEY `page_views_created_at_index` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_10_07_000100_create_page_views_table', COALESCE(MAX(`batch`), 0) + 1 FROM `migrations`;
