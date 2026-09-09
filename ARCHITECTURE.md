# Architecture

## Runtime stack

- FastAdmin application on ThinkPHP 5.0.x.
- PHP requirement declared as `>=5.6.0`.
- MySQL persistence through ThinkPHP `Db` / models.
- `application/admin` provides the management UI and CRUD controllers.
- `application/index` provides the public website and software-source endpoints.
- `application/api` is the stock FastAdmin API module and is mostly independent from the software-source domain.
- `addons`, `thinkphp`, and `vendor` are bundled framework/dependency code rather than core software-source business logic.

## Software-source domain

### Public endpoints

`application/route.php` maps:

- `/appstore` -> `index/App/list`
- `/log` -> `index/App/log`

`application/index/controller/Index.php` also exposes the web front page and `apiface()` license-status lookup.

### Main data model

The project reuses legacy FastAdmin tables/fields for the software-source domain:

- `fa_category`: application catalog. Important legacy field mappings include `nickname -> version`, `bt1a -> downloadURL`, `bt1b -> tintColor`, `bt2a -> size`, `bt2b -> lock`, `flag -> isLanZouCloud`.
- `fa_config`: source metadata and switches (`name`, `message`, `sourceURL`, `sourceicon`, `payURL`, `unlockURL`, `identifier`, `opencry`, `openblack`, `openblack2`).
- `fa_kami`: unlock codes and UDID binding/expiry state.
- `fa_black`: blocked UDIDs.
- `fa_monitor`: observed UDID activity.

### `/appstore` request flow

1. Read optional JSON request body `value` and decode the legacy `udid1|udid2` monitor payload.
2. Apply `openblack/openblack2` rules to `fa_black` and `fa_monitor`.
3. Read `udid` and optional unlock `code` from query parameters.
4. If the UDID is blocked, return the legacy blocked-source payload.
5. If `code` is present, activate/bind the matching `fa_kami` record.
6. Otherwise determine license state from the most recent `fa_kami` row for the UDID.
7. Load active apps from `fa_category` and source metadata from `fa_config`.
8. Convert legacy database rows into the public AppStore JSON shape.
9. If `opencry=1`, base64 the JSON and POST it to the legacy remote encryption endpoint, returning `{ "appstore": ... }`; otherwise return plain JSON.

## Phase-1 refactor boundary

`application/index/service/AppSourceBuilder.php` owns the pure row-to-payload transformation. Database access, authorization decisions, remote encryption, and exact response handling remain in `App.php` so external behavior is not intentionally changed.
