<?php

namespace Gazelle\Schedule\Tasks;

class Recovery extends \Gazelle\Schedule\Task
{
    public function run()
    {
        if (defined('RECOVERY_AUTOVALIDATE') && RECOVERY_AUTOVALIDATE) {
            \Gazelle\Recovery::validate_pending($this->db);
        }

        \Gazelle\Recovery::boost_upload($this->db, $this->cache);
    }
}
