<?php

namespace Gazelle\Comment;

class Artist extends AbstractComment {
    public function page(): string {
        return 'artist';
    }

    public function pageUrl(): string {
        return "artist.php?id=";
    }
}
