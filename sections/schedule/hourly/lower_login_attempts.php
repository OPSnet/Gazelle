<?php

//------------- Lower Login Attempts ------------------------------------//
$DB->query("
        UPDATE login_attempts
        SET Attempts = Attempts - 1
        WHERE Attempts > 0");
$DB->query("
        DELETE FROM login_attempts
        WHERE LastAttempt < '".time_minus(3600 * 24 * 90)."'");
