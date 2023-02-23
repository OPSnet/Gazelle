<?php

if (!$Viewer->permitted('site_moderate_forums') || empty($_POST['id']) || empty($_POST['remove'])) {
    json_error('bad parameters');
}

Gazelle\DB::DB()->prepared_query("
    UPDATE reports SET ClaimerID = 0 WHERE ID = ?
    ", (int)$_POST['id']
);

print json_encode([
    'status' => 'success',
]);
