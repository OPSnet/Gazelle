<?php

enforce_login();
authorize();
$Viewer->logout();
header("Location: /index.php");
