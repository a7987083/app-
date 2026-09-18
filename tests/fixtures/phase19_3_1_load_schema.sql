-- Phase 19.3.1 real HTTP load-test fixture.
-- This schema is intentionally minimal and exists only inside CI.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS fa_api_request_log;
DROP TABLE IF EXISTS fa_api_endpoint;
DROP TABLE IF EXISTS fa_source_change;
DROP TABLE IF EXISTS fa_kami_app;
DROP TABLE IF EXISTS fa_kami;
DROP TABLE IF EXISTS fa_black;
DROP TABLE IF EXISTS fa_category;
DROP TABLE IF EXISTS fa_config;

CREATE TABLE fa_config (
  id int unsigned NOT NULL AUTO_INCREMENT,
  name varchar(64) NOT NULL DEFAULT '',
  value text,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE fa_category (
  id int unsigned NOT NULL AUTO_INCREMENT,
  type varchar(30) NOT NULL DEFAULT '1',
  name varchar(120) NOT NULL DEFAULT '',
  nickname varchar(60) NOT NULL DEFAULT '',
  keywords text,
  bt1a varchar(512) NOT NULL DEFAULT '',
  bt1b varchar(32) NOT NULL DEFAULT '',
  bt2a varchar(32) NOT NULL DEFAULT '',
  bt2b varchar(8) NOT NULL DEFAULT '0',
  renewal_entry tinyint unsigned NOT NULL DEFAULT 0,
  flag varchar(8) NOT NULL DEFAULT '0',
  image varchar(512) NOT NULL DEFAULT '',
  updatetime int unsigned NOT NULL DEFAULT 0,
  weigh int NOT NULL DEFAULT 0,
  status varchar(30) NOT NULL DEFAULT 'hidden',
  PRIMARY KEY (id),
  KEY idx_status_weigh (status,weigh)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE fa_black (
  id int unsigned NOT NULL AUTO_INCREMENT,
  udid varchar(128) NOT NULL DEFAULT '',
  addtime int unsigned NOT NULL DEFAULT 0,
  usetime int unsigned NOT NULL DEFAULT 0,
  endtime int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE fa_kami (
  id int unsigned NOT NULL AUTO_INCREMENT,
  kami varchar(128) NOT NULL DEFAULT '',
  udid varchar(128) NOT NULL DEFAULT '',
  jh tinyint unsigned NOT NULL DEFAULT 0,
  endtime int unsigned NOT NULL DEFAULT 0,
  card_scope tinyint unsigned NOT NULL DEFAULT 1,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE fa_kami_app (
  id int unsigned NOT NULL AUTO_INCREMENT,
  kami_id int unsigned NOT NULL DEFAULT 0,
  app_id int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE fa_source_change (
  revision bigint unsigned NOT NULL AUTO_INCREMENT,
  app_id int unsigned NOT NULL DEFAULT 0,
  action varchar(16) NOT NULL DEFAULT 'update',
  changed_at int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (revision),
  KEY idx_app_revision (app_id,revision)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO fa_config(name,value) VALUES
('name','Phase19.3.1 Load Test'),
('message','Real HTTP concurrency fixture'),
('identifier','phase19.3.1'),
('sourceURL','http://127.0.0.1:18080/appstore'),
('sourceicon','https://example.invalid/icon.png'),
('payURL','https://example.invalid/pay'),
('unlockURL','https://example.invalid/unlock'),
('opencry','0'),
('openblack','0'),
('openblack2','0');

INSERT INTO fa_kami(kami,udid,jh,endtime,card_scope)
VALUES ('LOADTEST-FULL-SOURCE','LOADTEST-LICENSED-UDID',1,2147483647,1);

INSERT INTO fa_category
(type,name,nickname,keywords,bt1a,bt1b,bt2a,bt2b,renewal_entry,flag,image,updatetime,weigh,status)
SELECT
  '1',
  CONCAT('LOADTEST-', LPAD(n + 1, 5, '0')),
  CONCAT('1.0.', n + 1),
  CONCAT('Phase 19.3.1 App ', n + 1, '\\nHTTP concurrency test'),
  CONCAT('https://example.invalid/apps/', n + 1, '.ipa'),
  '#336699',
  CAST(104857600 + n AS CHAR),
  IF(MOD(n, 3) = 0, '1', '0'),
  0,
  '0',
  CONCAT('https://example.invalid/icons/', n + 1, '.png'),
  UNIX_TIMESTAMP() - MOD(n, 86400),
  20000 - n,
  'hidden'
FROM (
  SELECT
    ones.n + tens.n*10 + hundreds.n*100 + thousands.n*1000 + ten_thousands.n*10000 AS n
  FROM
    (SELECT 0 n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) ones
  CROSS JOIN
    (SELECT 0 n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) tens
  CROSS JOIN
    (SELECT 0 n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) hundreds
  CROSS JOIN
    (SELECT 0 n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) thousands
  CROSS JOIN
    (SELECT 0 n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) ten_thousands
) seq
WHERE n < 20000;

SET FOREIGN_KEY_CHECKS=1;
