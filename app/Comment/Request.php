<?php

namespace Gazelle\Comment;

class Request extends AbstractComment {
    public function page(): string {
        return 'requests';
    }

    public function pageUrl(): string {
        return "requests.php?action=view&id=";
    }
}
