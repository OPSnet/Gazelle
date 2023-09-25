<?php

namespace Gazelle\Json;

class ArtistSimilar extends \Gazelle\Json {
    public function __construct(
        protected \Gazelle\Artist $artist,
        protected int             $limit,
    ) {}

    public function payload(): array {
        return array_slice($this->artist->similar()->info(), 0, $this->limit);
    }
}
