<?php

namespace Gazelle\CommentViewer;

class Artist extends \Gazelle\CommentViewer {
    public function __construct(int $viewerId, int $artistId) {
        parent::__construct($viewerId);
        $this->baseLink = "artist.php?id={$artistId}";
        $this->page     = 'artist';
    }
}
