<?php

authorize();

$type = $_GET['type'] ??  '';
$id   = $_GET['id'] ??  '';
if (!(new Gazelle\User\Bookmark($Viewer))->create($type, $id)) {
    json_error('bad parameters');
}

if ($type === 'request') {
    (new Gazelle\Manager\Request())->findById($id)?->updateBookmarkStats();
}
print(json_encode('OK'));
