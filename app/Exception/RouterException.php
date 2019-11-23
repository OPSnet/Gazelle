<?php

namespace Gazelle\Exception;

use Throwable;

class RouterException extends \RuntimeException {
    public function __construct(string $message =  "The route you tried to access is not available.", int $code = 0, Throwable $previous = null) {
        parent::__construct($message, $code, $previous);
    }
}
