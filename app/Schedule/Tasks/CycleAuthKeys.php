<?php

namespace Gazelle\Schedule\Tasks;

class CycleAuthKeys extends \Gazelle\Schedule\Task
{
    public function run()
    {
        $this->db->prepared_query("
                UPDATE users_info
                SET AuthKey =
                    MD5(
                        CONCAT(
                            AuthKey, RAND(), ?,
                            SHA1(
                                CONCAT(
                                    RAND(), RAND(), ?
                                )
                            )
                        )
                    );
        ", \Users::make_secret(), \Users::make_secret());
    }
}
