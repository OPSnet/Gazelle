<?php

namespace Gazelle\Request;

class Encoding extends AbstractValue {
    protected function legal(): array {
        return ENCODING;
    }
}
