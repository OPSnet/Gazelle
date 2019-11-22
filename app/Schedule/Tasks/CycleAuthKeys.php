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

        $this->db->prepared_query("
            SELECT max(ID)
            FROM users_main
        ");
        list($maxId) = $this->db->next_record();

        for ($i = 1; $i <= $maxId; $i++) {
            $this->cache->delete_value("user_info_heavy_$i");
        }
    }
}
