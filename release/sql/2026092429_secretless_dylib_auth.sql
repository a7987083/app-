-- 2429: remove client Verify Secret and add device-key challenge authentication.
-- MySQL 5.7 compatible.

CREATE TABLE IF NOT EXISTS `fa_dylib_device_key` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `udid_hash` char(64) NOT NULL,
  `dylib_id` bigint unsigned NOT NULL,
  `key_id` varchar(64) NOT NULL,
  `algorithm` varchar(32) NOT NULL DEFAULT 'ecdsa-p256-sha256',
  `public_key_pem` text NOT NULL,
  `public_key_hash` char(64) NOT NULL,
  `status` varchar(16) NOT NULL DEFAULT 'active',
  `enrolled_at` int unsigned NOT NULL DEFAULT '0',
  `last_used_at` int unsigned NOT NULL DEFAULT '0',
  `revoked_at` int unsigned NOT NULL DEFAULT '0',
  `created_at` int unsigned NOT NULL DEFAULT '0',
  `updated_at` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_dylib_device` (`udid_hash`,`dylib_id`),
  UNIQUE KEY `uk_key_id` (`key_id`),
  KEY `idx_key_status` (`dylib_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Secretless Dylib device public keys';

CREATE TABLE IF NOT EXISTS `fa_dylib_auth_challenge` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `challenge_id` char(32) NOT NULL,
  `challenge_hash` char(64) NOT NULL,
  `udid_hash` char(64) NOT NULL,
  `dylib_id` bigint unsigned NOT NULL,
  `public_key_hash` char(64) NOT NULL,
  `expires_at` int unsigned NOT NULL DEFAULT '0',
  `consumed_at` int unsigned NOT NULL DEFAULT '0',
  `created_at` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_challenge_id` (`challenge_id`),
  KEY `idx_challenge_expiry` (`expires_at`,`consumed_at`),
  KEY `idx_challenge_device` (`udid_hash`,`dylib_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='One-time Dylib device authentication challenges';

ALTER TABLE `fa_dylib_runtime_config`
  ADD COLUMN `signing_private_key_ciphertext` mediumtext NULL AFTER `verify_path`,
  ADD COLUMN `signing_public_key_pem` text NULL AFTER `signing_private_key_ciphertext`,
  ADD COLUMN `signing_key_id` varchar(64) NOT NULL DEFAULT '' AFTER `signing_public_key_pem`;

ALTER TABLE `fa_dylib`
  DROP COLUMN `verify_secret_ciphertext`;

DROP TABLE IF EXISTS `fa_dylib_nonce`;
