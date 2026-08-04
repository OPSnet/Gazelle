<?php

namespace Gazelle\Enum;

enum Autocomplete: int {
    case everywhere = 0;
    case disabled   = 1;
    case searchOnly = 2;
}
