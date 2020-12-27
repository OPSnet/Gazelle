<?php

namespace Gazelle\Comment;

class Torrent extends AbstractComment {
    public function page(): string {
        return 'torrents';
    }

    public function pageUrl(): string {
        return "torrents.php?id=";
    }
}
