<?php

echo (new Gazelle\Json\Stats\User(new Gazelle\Stats\Users))
    ->setVersion(2)
    ->response();
