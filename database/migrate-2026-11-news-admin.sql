-- Ejecutar en phpMyAdmin sobre la base existente. No borra registros.
ALTER TABLE news_posts ADD COLUMN IF NOT EXISTS body_html MEDIUMTEXT NULL AFTER body;

CREATE TABLE IF NOT EXISTS admin_users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username VARCHAR(80) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_username (username),
  CONSTRAINT chk_admin_username_not_empty CHECK (CHAR_LENGTH(TRIM(username)) >= 3),
  CONSTRAINT chk_admin_is_active CHECK (is_active IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
