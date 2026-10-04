-- MySQL: таблица офферов для YML Яндекс.Бизнеса (акционные туры Tourvisor).
-- Применение: mysql -u USER -p DB < backend/scripts/create_yandex_feed_offers_mysql.sql
-- Либо таблица создаётся автоматически при первом вызове yandex_feed_ensure_table() в PHP.

CREATE TABLE IF NOT EXISTS yandex_feed_offers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tourvisor_tour_id VARCHAR(64) NOT NULL,
  country_id INT NOT NULL,
  country_name VARCHAR(255) NOT NULL,
  title VARCHAR(500) NOT NULL,
  description TEXT,
  picture_url VARCHAR(2000) NOT NULL,
  price DECIMAL(12,2) NOT NULL,
  offer_url VARCHAR(2000) NOT NULL,
  enabled TINYINT(1) NOT NULL DEFAULT 1,
  synced_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_yandex_tour (tourvisor_tour_id),
  KEY idx_yandex_country (country_id),
  KEY idx_yandex_enabled (enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
