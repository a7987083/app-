-- Phase 19.2 / 2026091802
-- Public AppStore V3 endpoints are retired. Legacy /appstore remains the only
-- client-facing source protocol. Keep fa_source_change: its monotonic revision
-- is now server-side cache invalidation infrastructure.

DELETE FROM `fa_config`
WHERE `name` = 'source_v3';
