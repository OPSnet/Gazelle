<?php

declare(strict_types=1);

namespace Gazelle;

echo new Json\Stats\General()
    ->setVersion(2)
    ->response();
