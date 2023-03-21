<?php

namespace Gazelle;

trait Pg {
    protected static \Gazelle\DB\Pg $pg;

    public function pg(): \Gazelle\DB\Pg {
        return $this->pg ??= new \Gazelle\DB\Pg(GZPG_DSN);
    }
}
