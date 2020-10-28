<?php

[$info] = (new Gazelle\Manager\Torrent)->setShowSnatched(false)->groupInfo((int)$_GET['id']);
if (!$info) {
    json_die('failure', 'bad id parameter');
}

json_print("success", [
    'wikiImage' => $info['WikiImage']
]);
