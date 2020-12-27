<?php

namespace Gazelle\Comment;

class Collage extends AbstractComment {
    public function page(): string {
        return 'collages';
    }

    public function pageUrl(): string {
        return "collages.php?action=comments&collageid=";
    }
}
