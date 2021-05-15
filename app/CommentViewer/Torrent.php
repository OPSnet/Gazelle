<?php

namespace Gazelle\CommentViewer;

class Torrent extends \Gazelle\CommentViewer {
    public function __construct(int $viewerId, int $groupId) {
        parent::__construct($viewerId);
        $this->baseLink = "torrents.php?id={$groupId}";
        $this->page     = 'torrent';
    }
}
