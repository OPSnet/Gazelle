<?php

echo (new Gazelle\Manager\User())->checkPassword($_REQUEST['password'] ?? '');
