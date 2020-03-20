<?php

namespace Gazelle\Exception;

use Throwable;

class InvalidAccessException extends \RuntimeException {
    public function __construct(string $message = 'You are not authorized to access this action', int $code = 0, Throwable $previous = null) {
        parent::__construct($message, $code, $previous);
    }
}
