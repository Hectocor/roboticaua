CREATE TABLE IF NOT EXISTS gallery_images (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(160) NOT NULL,
  image_path VARCHAR(500) NOT NULL,
  alt_text VARCHAR(220) NOT NULL DEFAULT '',
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_gallery_visible_order (is_active, sort_order, id),
  CONSTRAINT chk_gallery_title_not_empty CHECK (CHAR_LENGTH(TRIM(title)) >= 2),
  CONSTRAINT chk_gallery_image_path_not_empty CHECK (CHAR_LENGTH(TRIM(image_path)) >= 1),
  CONSTRAINT chk_gallery_is_active CHECK (is_active IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS site_alerts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(160) NOT NULL,
  message TEXT NOT NULL,
  image_path VARCHAR(500) NOT NULL DEFAULT '',
  link_url VARCHAR(500) NOT NULL DEFAULT '',
  link_label VARCHAR(80) NOT NULL DEFAULT '',
  is_active TINYINT(1) NOT NULL DEFAULT 0,
  starts_at DATETIME NULL,
  ends_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_site_alert_active (is_active, starts_at, ends_at, id),
  CONSTRAINT chk_site_alert_title_not_empty CHECK (CHAR_LENGTH(TRIM(title)) >= 2),
  CONSTRAINT chk_site_alert_message_not_empty CHECK (CHAR_LENGTH(TRIM(message)) >= 8),
  CONSTRAINT chk_site_alert_is_active CHECK (is_active IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS news_posts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(180) NOT NULL,
  summary VARCHAR(320) NOT NULL DEFAULT '',
  body TEXT NOT NULL,
  image_path VARCHAR(500) NOT NULL DEFAULT '',
  link_url VARCHAR(500) NOT NULL DEFAULT '',
  link_label VARCHAR(80) NOT NULL DEFAULT '',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  published_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_news_public (is_active, published_at, id),
  CONSTRAINT chk_news_title_not_empty CHECK (CHAR_LENGTH(TRIM(title)) >= 2),
  CONSTRAINT chk_news_body_not_empty CHECK (CHAR_LENGTH(TRIM(body)) >= 12),
  CONSTRAINT chk_news_is_active CHECK (is_active IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO gallery_images (title, image_path, alt_text, sort_order, is_active, created_at, updated_at) VALUES
('Actividad 12', 'gallery/Actividad 12.JPG', 'Actividad 12', 10, 1, '2026-05-01 10:00:00', '2026-05-01 10:00:00'),
('Actividad 11', 'gallery/Actividad 11.JPG', 'Actividad 11', 20, 1, '2026-04-21 10:00:00', '2026-04-21 10:00:00'),
('Actividad 10', 'gallery/Actividad 10.JPG', 'Actividad 10', 30, 1, '2026-04-11 10:00:00', '2026-04-11 10:00:00'),
('Actividad 9', 'gallery/Actividad 9.JPG', 'Actividad 9', 40, 1, '2026-04-01 10:00:00', '2026-04-01 10:00:00'),
('Actividad 8', 'gallery/Actividad 8.JPG', 'Actividad 8', 50, 1, '2026-03-22 10:00:00', '2026-03-22 10:00:00'),
('Actividad 7', 'gallery/Actividad 7.JPG', 'Actividad 7', 60, 1, '2026-03-12 10:00:00', '2026-03-12 10:00:00'),
('Actividad 6', 'gallery/Actividad 6.jpeg', 'Actividad 6', 70, 1, '2026-03-02 10:00:00', '2026-03-02 10:00:00'),
('Actividad 5', 'gallery/Actividad 5.jpeg', 'Actividad 5', 80, 1, '2026-02-20 10:00:00', '2026-02-20 10:00:00'),
('Actividad 4', 'gallery/Actividad 4.jpeg', 'Actividad 4', 90, 1, '2026-02-10 10:00:00', '2026-02-10 10:00:00'),
('Actividad 3', 'gallery/Actividad 3.jpeg', 'Actividad 3', 100, 1, '2026-01-31 10:00:00', '2026-01-31 10:00:00'),
('Actividad 2', 'gallery/Actividad 2.jpeg', 'Actividad 2', 110, 1, '2026-01-21 10:00:00', '2026-01-21 10:00:00'),
('Antupehuén 1', 'gallery/Antupehuén 1.jpeg', 'Antupehuén 1', 120, 1, '2026-01-11 10:00:00', '2026-01-11 10:00:00'),
('Actividad 1', 'gallery/actividad-01.jpg', 'Actividad 1', 130, 1, '2026-01-01 10:00:00', '2026-01-01 10:00:00');
