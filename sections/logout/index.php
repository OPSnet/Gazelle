<?php

authorize();
$Viewer->logout();
header("Location: /index.php");
