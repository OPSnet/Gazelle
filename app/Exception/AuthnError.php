<?php

namespace Gazelle\Exception;

use Gazelle\Enum\AuthnMechanism;

class AuthnError extends \Exception {
    public function __construct(string $message, public readonly AuthnMechanism $mechanism) {
        parent::__construct($message);
    }
}
