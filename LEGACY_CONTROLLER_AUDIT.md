# Legacy Controller Access Audit

`application/index/controller/App-mb.php` and `Index2.php` are stale duplicate
controllers. Static repository review found no route/reference, but production
history must be checked before deleting files from an existing site.

Run on the BaoTa server:

```bash
bash tools/legacy_controller_access_audit.sh /www/wwwlogs
```

If the result is `OK` and the inspected logs cover the production history you
care about, remove them with the guarded mode:

```bash
bash tools/legacy_controller_access_audit.sh \
  --delete /www/wwwroot/app3.zonoeios.xyz \
  /www/wwwlogs
```

If any matching request is found, the script exits with code `2` and refuses
to delete. If no log files can be found, it exits with code `3` and also
refuses to delete.
