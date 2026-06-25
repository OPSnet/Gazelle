<?php

namespace Gazelle\Intf;

use Gazelle\Enum\BookmarkType;

interface Bookmarked {
    public function id(): int;

    public function bookmarkType(): BookmarkType;
}
