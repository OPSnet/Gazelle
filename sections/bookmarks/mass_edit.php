<?php

/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

authorize();

if ($_POST['type'] === 'torrents') {
    $bookmarker = new User\Bookmark($Viewer);
    if (isset($_POST['update']) && $_POST['sort'] !== []) {
        $bookmarker->modifyList($_POST['sort']);
    } elseif (isset($_POST['delete'])) {
        $remove = array_keys($_POST['remove']);
        if ($remove !== []) {
            $bookmarker->removeList($remove);
        }
    }
}

header('Location: bookmarks.php?type=torrents');
