<?php

namespace Gazelle\CommentViewer;

class Artist extends \Gazelle\CommentViewer {

    public function __construct(\Twig\Environment $twig, int $viewerId, int $artistId) {
        parent::__construct($twig, $viewerId);
        $this->baseLink = "artist.php?id={$artistId}&postid=%d#post%d";
        $this->page     = 'artist';
    }
}
