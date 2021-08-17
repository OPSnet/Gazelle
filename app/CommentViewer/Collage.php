<?php

namespace Gazelle\CommentViewer;

class Collage extends \Gazelle\CommentViewer {
    public function __construct(int $viewerId, int $collageId) {
        parent::__construct($viewerId);
        $this->baseLink = "collages.php?action=comments&collageid={$collageId}";
        $this->page     = 'collages';
    }
}
