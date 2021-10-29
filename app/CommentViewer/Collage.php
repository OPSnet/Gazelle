<?php

namespace Gazelle\CommentViewer;

class Collage extends \Gazelle\CommentViewer {
    public function __construct(\Gazelle\User $viewer, int $collageId) {
        parent::__construct($viewer);
        $this->baseLink = "collages.php?action=comments&collageid={$collageId}";
        $this->page     = 'collages';
    }
}
