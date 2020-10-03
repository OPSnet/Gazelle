<?php

namespace Gazelle\CommentViewer;

class Request extends \Gazelle\CommentViewer {

    public function __construct(\Twig\Environment $twig, int $viewerId, int $requestId) {
        parent::__construct($twig, $viewerId);
        $this->baseLink = "requests.php?action=view&id={$requestId}&postid=%d#post%d";
        $this->page     = 'request';
    }
}
