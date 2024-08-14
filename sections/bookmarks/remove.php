<?php
/** @phpstan-var \Gazelle\User $Viewer */

authorize();

if ((new Gazelle\User\Bookmark($Viewer))->remove($_GET['type'], (int)$_GET['id'])) {
    print(json_encode('OK'));
} else {
    json_error('bad parameters');
}
