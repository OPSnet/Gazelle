<?php

enforce_login();

$DB->query("
SELECT r1.ID
FROM torrents_group AS r1 JOIN
   (SELECT (RAND() *
                 (SELECT MAX(ID)
                    FROM torrents_group)) AS ID)
    AS r2
WHERE r1.ID >= r2.ID
ORDER BY r1.ID ASC
LIMIT 1");
$torrent = $DB->next_record();
header("Location: torrents.php?id={$torrent['ID']}");