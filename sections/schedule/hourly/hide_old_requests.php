<?php

//------------- Hide old requests ---------------------------------------//
sleep(3);
$DB->query("
		UPDATE requests
		SET Visible = 0
		WHERE TimeFilled < (NOW() - INTERVAL 7 DAY)
			AND TimeFilled != '0000-00-00 00:00:00'");
