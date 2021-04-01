<?php

enforce_login();
authorize();
(new Gazelle\User($LoggedUser['ID']))->logout();
header("Location: /index.php");
