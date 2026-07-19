<?php

/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

authorize();

new User\Bookmark($Viewer)->removeSnatched();

header('Location: bookmarks.php');
