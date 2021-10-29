<?php

namespace Gazelle\CommentViewer;

class Torrent extends \Gazelle\CommentViewer {
    public function __construct(\Gazelle\User $viewer, int $groupId) {
        parent::__construct($viewer);
        $this->baseLink = "torrents.php?id={$groupId}";
        $this->page     = 'torrents';
    }
}
