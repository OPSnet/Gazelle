<?php

/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

authorize();

$id = (int)($_GET['id'] ?? 0);
if ($id === 0) {
    json_error('bad id');
}
$object = match ($_GET['type'] ?? '') {
    'artist'  => new Manager\Artist()->findById($id),
    'collage' => new Manager\Collage()->findById($id),
    'request' => new Manager\Request()->findById($id),
    'torrent' => new Manager\TGroup()->findById($id),
    default   => json_error('bad type'),
};
if ($object instanceof Intf\Bookmarked && new User\Bookmark($Viewer)->removeObject($object)) {
    print(json_encode('OK'));
} else {
    json_error('not bookmarked');
}
