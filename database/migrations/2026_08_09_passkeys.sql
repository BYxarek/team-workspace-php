CREATE TABLE IF NOT EXISTS user_passkeys (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(80) NOT NULL,
  credential_id BLOB NOT NULL,
  credential_id_hash CHAR(64) NOT NULL UNIQUE,
  public_key TEXT NOT NULL,
  sign_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
  transports VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  last_used_at TIMESTAMP NULL,
  INDEX(user_id),
  CONSTRAINT fk_passkey_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO system_settings(`key`,`value`) VALUES ('password_login_enabled','1') ON DUPLICATE KEY UPDATE `key`=`key`;
