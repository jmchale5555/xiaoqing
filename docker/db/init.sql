-- Intentionally minimal.
-- Schema is managed via class-based migrations in database/migrations.
CREATE DATABASE IF NOT EXISTS phpsk_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON phpsk_test.* TO 'phpsk'@'%';
FLUSH PRIVILEGES;
