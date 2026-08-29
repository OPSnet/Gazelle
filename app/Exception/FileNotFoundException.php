<?php

namespace Gazelle\Exception;

class FileNotFoundException extends \Exception {
    public function __construct(public readonly string $filename) {}
}
