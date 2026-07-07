<?php

namespace Gazelle\Enum;

/* The label/value pairs look redundant but are necessary for pg_wrapper's enum mapper */

enum RiplogChecksum: string {
    case ok      = 'ok';
    case invalid = 'invalid';
    case missing = 'missing';
}
