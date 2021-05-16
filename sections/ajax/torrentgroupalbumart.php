<?php

$tgroup = (new Gazelle\Manager\TGroup)->findById((int)$_GET['id']);
if (is_null($tgroup)) {
    json_die('failure', 'bad id parameter');
}

json_print("success", [
    'wikiImage' => $tgroup->info()['WikiImage'],
]);
