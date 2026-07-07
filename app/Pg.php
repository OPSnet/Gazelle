<?php

declare(strict_types=1);

namespace Gazelle;

trait Pg {
    protected static DB\Pg $pgro; // R/O access
    protected static DB\Pg $pg;   // R/W access

    public function pgro(): DB\Pg {
        return self::$pgro ??= new DB\Pg(PG_RO_DSN);
    }

    public function pg(): DB\Pg {
        return self::pgStatic();
    }

    // disgusting hack required for \View class
    public static function pgStatic(): DB\Pg {
        return self::$pg ??= new DB\Pg(PG_RW_DSN);
    }
}
