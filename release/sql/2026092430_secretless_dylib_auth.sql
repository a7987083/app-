-- 2430: Secretless Dylib authentication rebuilt from 2026092428.
-- MySQL 5.7 compatible and idempotent.

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='One-time Dylib authentication challenges';

SET @c := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fa_dylib' AND COLUMN_NAME='verify_secret_ciphertext');
SET @q := IF(@c>0,'ALTER TABLE `fa_dylib` DROP COLUMN `verify_secret_ciphertext`','SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

DROP TABLE IF EXISTS `fa_dylib_nonce`;

UPDATE `fa_dylib_runtime_config`
SET `config_version`=GREATEST(`config_version`,3)
WHERE `id`=1;
