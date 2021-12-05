<?php

namespace Gazelle\Schedule\Tasks;

class CycleAuthKeys extends \Gazelle\Schedule\Task
{
    public function run()
    {
        self::$db->prepared_query("
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
        ", randomString(), randomString());

        self::$db->prepared_query("
            SELECT ID FROM users_main
        ");
        $keys = self::$db->collect(0, false);
        foreach ($keys as $key) {
            self::$cache->delete_value("u_$key");
        }
    }
}
