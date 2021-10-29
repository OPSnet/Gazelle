<?php

namespace Gazelle\CommentViewer;

class Request extends \Gazelle\CommentViewer {
    public function __construct(\Gazelle\User $viewer, int $requestId) {
        parent::__construct($viewer);
        $this->baseLink = "requests.php?action=view&id={$requestId}";
        $this->page     = 'requests';
    }
}
