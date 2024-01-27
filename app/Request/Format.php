<?php

namespace Gazelle\Request;

class Format extends AbstractValue {
    protected function legal(): array {
        return FORMAT;
    }
}
