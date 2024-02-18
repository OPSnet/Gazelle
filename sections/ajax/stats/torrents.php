<?php

echo (new Gazelle\Json\Stats\Torrent(new Gazelle\Stats\Torrent()))
    ->setVersion(2)
    ->response();
