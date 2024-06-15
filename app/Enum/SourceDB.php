<?php

namespace Gazelle\Enum;

enum SourceDB: string {
    case mysql    = 'mysql';
    case postgres = 'pg';
}
