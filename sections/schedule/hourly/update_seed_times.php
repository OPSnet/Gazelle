<?php
//------------- Update seed times ---------------------------------------//
// TODO: move this into ocelot itself
$DB->query("
INSERT INTO xbt_files_history (uid, fid, seedtime)
  SELECT DISTINCT uid, fid, 1
  FROM xbt_files_users
  WHERE active = 1 AND remaining = 0 AND mtime > unix_timestamp(NOW() - INTERVAL 1 HOUR)
ON DUPLICATE KEY UPDATE seedtime = seedtime + 1");
