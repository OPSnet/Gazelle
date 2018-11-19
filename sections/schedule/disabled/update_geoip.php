<?php

$DB->query("INSERT INTO users_geodistribution
	(Code, Users)
SELECT g.Code, COUNT(u.ID) AS Users
FROM geoip_country AS g
	JOIN users_main AS u ON INET_ATON(u.IP) BETWEEN g.StartIP AND g.EndIP
WHERE u.Enabled = '1'
GROUP BY g.Code
ORDER BY Users DESC");
