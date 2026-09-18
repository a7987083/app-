# Software Source Development Handoff

## Repository / baselines

- Repository: `a7987083/app-`
- Stable branch: `release/2026091804-phase19-3-1-concurrency-api-center`
- Stable version/phase: `2026091804 / Phase 19.3.1`
- Stable commit: `34fc713346eafe92f23d1f5fcf470526daf8ddb6`
- Stable release: `source-v2026091804`
- Active development branch: `feature/2026091805-phase19-4-dynamic-announcement-license`
- Phase19.4 code commit: `391de96f6a8f4da3d1f75b505acc876b07101608`
- Phase19.4 CI Run: `35296525450` — SUCCESS
- VERSION is intentionally still `2026091804`; do not call this a released 1805 build yet.

## Phase 19.4 implementation

Public source path remains:

`/appstore -> App::list -> AppStorePayload -> SourceLegacyCache/SourceResponse -> encryption -> transport`.

Dynamic announcements are implemented by `SourceAnnouncementTemplate`.

Critical cache boundary:

1. Source config keeps the original announcement template.
2. Shared plain/encrypted JSON caches store a fixed sentinel instead of per-UDID rendered text.
3. Current-request context is calculated from app rows and card scopes.
4. Cached JSON/body is retrieved.
5. Sentinel is replaced with the rendered announcement.
6. Encryption/transport runs afterwards.

This prevents full-source/verify/App-scope/guest announcement state from contaminating shared 120-second caches.

Supported variables:
`[刷新时间]`, `[软件个数]`, `[今日更新]`, `[七日更新]`, `[授权状态]`, `[全源到期时间]`, `[全源剩余时间]`, `[验证到期时间]`, `[指定APP数量]`, `[授权摘要]`, `[源名称]`, `[服务器时间]`.
Default syntax: `[变量|默认值]`.

Admin General Config now exposes clickable tokens and live preview. Preview without UDID behaves as guest; with UDID it reads that device's current cards.

## /license fix

The source route/controller/view already existed. The 404 root cause was production Nginx case-insensitive security matching of `LICENSE`.

Repository contract now defines:

- exact `/LICENSE` -> 404
- exact `/license` -> ThinkPHP rewrite
- generic `location /` unchanged

Online-update manifest now includes:

- `application/common/library/AuthorizationLicense.php`
- `application/index/view/index/license.html`
- `application/index/view/index/unbind.html`
- `nginx.rewrite`

Important: copying `nginx.rewrite` into the project does not prove the active BaoTa Nginx vhost has reloaded it. Production verification is still required.

## CI evidence

Run `35296525450`, Job `php70-contract`:

- PHP 7.0 lint: success
- JS syntax: success
- `phase19_4_dynamic_announcement_test`: success
- `source_response_test`: success
- Legacy response cache regression: success
- Phase19.3.1 API Center regression: success
- deployment contract: success
- card access / App scope regressions: success
- online-update package build and required file assertions: success

## Pre-existing 1804 release note issue

Stable commit Run `35291515730`:
PHP 7.0, MySQL 5.7, HTTP load and package-and-release jobs all succeeded. Final online-update E2E failed because `phase13_github_online_update_e2e.php` requires Release Notes changelog to contain literal Chinese `更新内容`. The 1805 candidate notes include this marker.

## Next task

1. Apply Nginx rewrite on a real BaoTa site and reload Nginx.
2. Verify `/LICENSE`, `/license` GET/POST and `/unbind`.
3. Run iOS source refresh against static and dynamic announcements in plain/normal/V2 modes and all card scopes.
4. If green, create 2026091805 release candidate metadata and run formal release/E2E.
