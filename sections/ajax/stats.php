<?php

echo (new Gazelle\Json\Stats\General(
    new Gazelle\Stats\Request(),
    new Gazelle\Stats\Torrent(),
    new Gazelle\Stats\Users(),
))
    ->setVersion(2)
    ->response();
