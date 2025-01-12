<?php

namespace Gazelle;

trait Pg {
    protected static \Gazelle\DB\Pg $pg;

    public function pg(): \Gazelle\DB\Pg {
        return self::pgStatic();
    }

    // disgusting hack required for \View class
    public static function pgStatic(): \Gazelle\DB\Pg {
        return self::$pg ??= new \Gazelle\DB\Pg(GZPG_DSN);
    }
}
