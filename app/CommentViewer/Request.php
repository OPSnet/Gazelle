<?php

namespace Gazelle\CommentViewer;

class Request extends \Gazelle\CommentViewer {
    public function __construct(int $viewerId, int $requestId) {
        parent::__construct($viewerId);
        $this->baseLink = "requests.php?action=view&id={$requestId}";
        $this->page     = 'requests';
    }
}
