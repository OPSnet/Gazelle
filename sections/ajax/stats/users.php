<?php
/** @phpstan-var \Gazelle\User $Viewer */

echo (new Gazelle\Json\Stats\User(
    new Gazelle\Stats\Users(),
    $Viewer,
))
    ->setVersion(2)
    ->response();
