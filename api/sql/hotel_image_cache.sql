-- Run once in phpMyAdmin (database garant77li) to stop hotel search 500s on missing cache table.
CREATE TABLE IF NOT EXISTS hotel_image_cache (
  hotel_id INT NOT NULL,
  picture_url VARCHAR(1024) NOT NULL,
  source VARCHAR(32) NOT NULL DEFAULT 'tourvisor',
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (hotel_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
