<?php

namespace Gazelle\CommentViewer;

class Artist extends \Gazelle\CommentViewer {
    public function __construct(\Gazelle\User $viewer, int $artistId) {
        parent::__construct($viewer);
        $this->baseLink = "artist.php?id={$artistId}";
        $this->page     = 'artist';
    }
}
