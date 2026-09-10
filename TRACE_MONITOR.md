# UDID 来源监控 / Trace Monitor

## Purpose

The historical `/appstore` endpoint accepts an optional JSON body field named `value`. The value is expected to be Base64 for:

```text
添加者UDID|破解者UDID
```

The first position maps to `openblack` and identity `添加者`; the second position maps to `openblack2` and identity `破解者`.

Only 25-character or 40-character values are treated as supported legacy UDIDs. This is a compatibility rule inherited from the original source backend; the server does not independently infer whether a device is really an adder or cracker.

## Behavior

When the corresponding auto-black switch is disabled:

```text
valid trace UDID -> fa_monitor
first observation -> count=1
later observation -> count+1
```

When the switch is enabled:

```text
valid trace UDID -> fa_black (permanent by default)
matching fa_monitor row -> removed after blacklist insert succeeds
```

A normal HTTP error counter, IP detector, User-Agent detector, rate limiter, or attack detector is not part of this module. The `count` field is a source-trace observation count.

## Admin terminology

Phase 6 clarifies the visible list labels:

- `身份` -> `来源身份`
- `异常请求次数` -> `来源记录次数`
- `首次记录时间` remains unchanged

This label change does not alter the database or protocol semantics.

## Terminal probe

Use the bundled script:

```bash
bash tools/trace_monitor_probe.sh https://example.com
```

Or supply explicit test UDIDs:

```bash
bash tools/trace_monitor_probe.sh \
  https://example.com/appstore \
  00008120-001A55A93AF0201E \
  00008110-001229DE2E82802E
```

Equivalent manual request:

```bash
UDID1='00008120-001A55A93AF0201E'
UDID2='00008110-001229DE2E82802E'
TRACE=$(printf '%s' "$UDID1|$UDID2" | base64 | tr -d '\r\n')

curl -i -X POST 'https://example.com/appstore' \
  -H 'Content-Type: application/json' \
  --data "{\"value\":\"$TRACE\"}"
```

## Database verification

With `openblack=0` and `openblack2=0`:

```sql
SELECT id,udid,identity,count,addtime
FROM fa_monitor
ORDER BY id DESC;
```

With auto-black enabled:

```sql
SELECT id,udid,addtime,usetime,endtime
FROM fa_black
ORDER BY id DESC;
```

Phase 5+ blacklist semantics are `usetime=0` for not yet hit and `endtime=0` for permanent.

## Fresh deployment seed

A fresh BaoTa deployment package should not preload historical `fa_monitor` observations. They are runtime observations, not application defaults. Phase 6 release packaging removes the two historical 2022 monitor rows that were inherited from the original database dump.
