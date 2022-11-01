<?php

namespace Gazelle;

abstract class ArtistRole extends \Gazelle\Base {
    public function __construct(
        protected readonly int $id,
    ) {}
}
