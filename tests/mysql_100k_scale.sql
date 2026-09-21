-- Seed exactly 100,000 IPA assets using set-based inserts for MySQL 5.7 CI scale testing.
INSERT INTO fa_ipa_source
(name, base_url, root_path, enabled, scan_page_size, request_timeout, created_at, updated_at)
VALUES ('scale', 'https://example.invalid', '/IPA', 1, 500, 20, UNIX_TIMESTAMP(), UNIX_TIMESTAMP());
SET @source_id := LAST_INSERT_ID();

CREATE TEMPORARY TABLE digits (n TINYINT UNSIGNED NOT NULL PRIMARY KEY);
INSERT INTO digits VALUES (0),(1),(2),(3),(4),(5),(6),(7),(8),(9);

INSERT INTO fa_ipa_asset
(source_id,path_hash,path,name,size_bytes,modified_at,status,bundle_id,app_name,app_version,build_version,last_seen_at,created_at,updated_at)
SELECT
  @source_id,
  SHA2(CONCAT('/IPA/Game-', x.n, '.ipa'), 256),
  CONCAT('/IPA/Game-', x.n, '.ipa'),
  CONCAT('Game-', x.n, '.ipa'),
  1048576 + x.n,
  UNIX_TIMESTAMP(),
  IF(MOD(x.n, 20)=0, 'parse_failed', 'parsed'),
  CONCAT('com.scale.game', x.n),
  CONCAT('Game ', x.n),
  CONCAT('1.', MOD(x.n,100)),
  CAST(x.n AS CHAR),
  UNIX_TIMESTAMP(),
  UNIX_TIMESTAMP(),
  UNIX_TIMESTAMP()
FROM (
  SELECT
    d0.n + d1.n*10 + d2.n*100 + d3.n*1000 + d4.n*10000 AS n
  FROM digits d0
  CROSS JOIN digits d1
  CROSS JOIN digits d2
  CROSS JOIN digits d3
  CROSS JOIN digits d4
) x;

SELECT COUNT(*) AS asset_count FROM fa_ipa_asset;
SELECT COUNT(*) AS failed_count FROM fa_ipa_asset WHERE source_id=@source_id AND status='parse_failed';
SELECT id,bundle_id FROM fa_ipa_asset WHERE bundle_id='com.scale.game99999' LIMIT 1;
EXPLAIN SELECT id FROM fa_ipa_asset WHERE source_id=@source_id AND status='parsed' ORDER BY id LIMIT 100;
EXPLAIN SELECT id FROM fa_ipa_asset WHERE bundle_id='com.scale.game99999' LIMIT 1;
