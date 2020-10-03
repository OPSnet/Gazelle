<?php

namespace Gazelle\CommentViewer;

class Torrent extends \Gazelle\CommentViewer {

    public function __construct(\Twig\Environment $twig, int $viewerId, int $groupId) {
        parent::__construct($twig, $viewerId);
        $this->baseLink = "torrents.php?id={$groupId}&postid=%d#post%d";
        $this->page     = 'torrent';
    }
}
