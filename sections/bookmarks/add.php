<?php

authorize();

if ((new Gazelle\User\Bookmark($Viewer))->create($_GET['type'], (int)$_GET['id'])) {
    print(json_encode('OK'));
} else {
    json_error('bad parameters');
}
