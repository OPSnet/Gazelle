<?php
/** @phpstan-var \Gazelle\User $Viewer */

if (!$Viewer->permitted('site_forum_autosub')) {
    json_error('forbidden');
}
authorize();

$forum = (new Gazelle\Manager\Forum())->findById((int)($_POST['id'] ?? 0));
if (is_null($forum)) {
    json_error('not found');
}

$active = (bool)$_POST['active'];
if ($forum->toggleAutoSubscribe($Viewer, $active)) {
    json_print('success', ['autosub' => $active]);
} else {
    json_error('unchanged');
}
