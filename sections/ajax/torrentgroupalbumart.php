<?php

declare(strict_types=1);

namespace Gazelle;

$tgroup = new Manager\TGroup()->findById((int)$_GET['id']);
if (is_null($tgroup)) {
    json_die('failure', 'bad id parameter');
}

json_print("success", [
    'wikiImage' => $tgroup->image()->url(),
]);
