<?php

enforce_login();

$DB->query("
SELECT r1.ID
FROM collages AS r1 JOIN
   (SELECT (RAND() *
                 (SELECT MAX(ID)
                    FROM collages)) AS ID)
    AS r2
WHERE r1.ID >= r2.ID
ORDER BY r1.ID ASC
LIMIT 1");
$collage = $DB->next_record();
header("Location: collages.php?id={$collage['ID']}");
