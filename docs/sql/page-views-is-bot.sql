-- Contrassegno "visita non umana" sulle visite delle statistiche (migrazione 2026_11_23_000100_add_is_bot_to_page_views).
-- Da eseguire UNA volta in phpMyAdmin sul database di KSM, PRIMA di mettere online il nuovo codice.

ALTER TABLE `page_views`
  ADD COLUMN `is_bot` TINYINT(1) NOT NULL DEFAULT 0,
  ADD INDEX `page_views_day_is_bot_index` (`day`, `is_bot`),
  ADD INDEX `page_views_host_day_is_bot_index` (`host`, `day`, `is_bot`);

-- Segna la migrazione come eseguita, cosi' un futuro "migrate" non la ripete.
INSERT INTO `migrations` (`migration`,`batch`) SELECT '2026_11_23_000100_add_is_bot_to_page_views', (SELECT MAX(`batch`) FROM (SELECT `batch` FROM `migrations`) b) FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM (SELECT `migration` FROM `migrations`) m WHERE m.`migration` = '2026_11_23_000100_add_is_bot_to_page_views');

-- FACOLTATIVO. Da solo, il giro orario (analytics:classify) esamina lo storico a scaglioni di 10 giorni
-- per volta, andando all'indietro: finche' non ha finito, i giorni vecchi risultano tutti "umani".
-- Per contrassegnare subito tutto lo storico esegui questo (puo' richiedere un minuto):
UPDATE `page_views` p
JOIN (
  SELECT `session_id` FROM `page_views`
  GROUP BY `session_id`
  HAVING SUM(`duration`) = 0 OR COUNT(*) > 40
) b ON b.`session_id` = p.`session_id`
SET p.`is_bot` = 1
WHERE p.`created_at` < NOW() - INTERVAL 2 HOUR;
