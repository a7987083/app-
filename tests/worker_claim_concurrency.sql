-- Minimal MySQL 5.7 fixture for validating competing worker claims.
INSERT INTO fa_ipa_source
(name, base_url, root_path, enabled, scan_page_size, request_timeout, created_at, updated_at)
VALUES ('concurrency', 'https://example.invalid', '/IPA', 1, 500, 20, UNIX_TIMESTAMP(), UNIX_TIMESTAMP());
SET @source_id := LAST_INSERT_ID();

INSERT INTO fa_ipa_scan_job
(source_id, mode, status, root_path, created_at, updated_at)
VALUES (@source_id, 'incremental', 'pending', '/IPA', UNIX_TIMESTAMP(), UNIX_TIMESTAMP());
SET @job_id := LAST_INSERT_ID();

INSERT INTO fa_ipa_scan_item
(job_id, source_id, item_type, path_hash, path, status, retry_count, available_at, worker_id, locked_at, created_at, updated_at)
SELECT @job_id, @source_id, 'directory', SHA2(CONCAT('/IPA/', n),256), CONCAT('/IPA/', n), 'pending', 0, UNIX_TIMESTAMP(), '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()
FROM (
  SELECT 1 n UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL
  SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9 UNION ALL SELECT 10
) q;
