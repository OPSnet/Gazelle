<?php

namespace Gazelle\Request;

class Media extends AbstractValue {
    protected function legal(): array {
        return MEDIA;
    }
}
