LOGCHECKERS
===========

Contained here are the .exe for the logcheckers for XLD and EAC which can run on Windows or under Wine for Linux,
needing only the command-line. Ideally, for wine, we'd use wineconsole, but it seems that it has a bug on Debian
in that it doesn't output anything which is less than ideal, so we use regular old wine and send errors to /dev/null
so we don't see wine complaining about how we haven't set $DISPLAY.


Sample runs:
```bash
vagrant@contrib-jessie:/vagrant$ wine logcheckers/eac_log_checker.exe logcheckers/log_files/eac_perfect.log 2>/dev/null
Log Integrity Checker   (C) 2010 by Andre Wiethoff

1. Log entry is fine!
```

```bash
vagrant@contrib-jessie:/vagrant$ wine logcheckers/eac_log_checker.exe logcheckers/log_files/eac_99.log 2>/dev/null
Log Integrity Checker   (C) 2010 by Andre Wiethoff

1. Log entry has no checksum!
```

(for XLD, it outputs on STDERR for some reason so we cannot route wine errors to `/dev/null`)
```bash
vagrant@contrib-jessie:/vagrant$ wine logcheckers/xld_log_checker.exe logcheckers/log_files/xld_perfect.log 2>&1
Application tried to create a window, but no driver could be loaded.
Make sure that your X server is running and that $DISPLAY is set correctly.
logcheckers/log_files/xld_perfect.log: OK
```