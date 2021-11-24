<?php

namespace Gazelle;

abstract class ArtistRole extends \Gazelle\Base {
    protected int $id;

    public function __construct(int $id) {
        parent::__construct();
        $this->id = $id;
    }
}
