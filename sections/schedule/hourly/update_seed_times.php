<?
//------------- Update seed times ---------------------------------------//

$DB->query("
  UPDATE xbt_snatched AS xs
  INNER JOIN xbt_files_users AS xfu
    ON xs.uid = xfu.uid AND xs.fid = xfu.fid
  SET xs.seedtime = xs.seedtime + xfu.active");
?>
