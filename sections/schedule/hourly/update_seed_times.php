<?php
//------------- Update seed times ---------------------------------------//

$DB->query("
  UPDATE xbt_snatched AS xs
  INNER JOIN (SELECT uid, fid, MAX(active) AS active FROM xbt_files_users GROUP BY uid,fid) AS xfu
    ON xs.uid = xfu.uid AND xs.fid = xfu.fid
  SET xs.seedtime = xs.seedtime + xfu.active");
