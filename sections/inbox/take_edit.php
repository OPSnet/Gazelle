<?php

authorize();

$pm = (new Gazelle\Manager\PM($Viewer))->findById((int)$_POST['convid']);
if (is_null($pm)) {
    error(404);
}
if (!$pm->isReadable()) {
    error(403);
}

if (isset($_POST['delete'])) {
    $pm->remove();
} else {
    $pm->pin(isset($_POST['pin']));
    if (isset($_POST['mark_unread'])) {
        $pm->markUnread();
    }
}
header("Location: inbox.php");
