<?php

namespace Gazelle\Exception;

class UserCreatorException extends \Exception {
    public function __construct(public readonly \Gazelle\Enum\UserCreatorError $error) {
        parent::__construct($error->name);
    }
}
