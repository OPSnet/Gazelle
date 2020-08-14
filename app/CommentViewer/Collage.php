<?php

namespace Gazelle\CommentViewer;

class Collage extends \Gazelle\CommentViewer {

    public function __construct(\Twig\Environment $twig, int $viewerId, int $collageId) {
        parent::__construct($twig, $viewerId);
        $this->baseLink = "collages.php?action=comments&amp;collageid=($collageId}&amp;postid=%d#post%d";
        $this->page     = 'collage';
    }
}
