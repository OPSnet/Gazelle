<?php

namespace Gazelle\Schedule\Tasks;

class ExpireInvites extends \Gazelle\Schedule\Task
{
    public function run()
    {
        self::$db->begin_transaction();
        self::$db->prepared_query("SELECT InviterID FROM invites WHERE Expires < now()");
        $list = self::$db->collect('InviterID', false);

        self::$db->prepared_query("DELETE FROM invites WHERE Expires < now()");
        self::$db->prepared_query("
            DELETE isp FROM invite_source_pending isp
            LEFT JOIN invites i ON (i.InviteKey = isp.invite_key)
            WHERE i.InviteKey IS NULL
        ");

        foreach ($list as $userId) {
            self::$db->prepared_query("UPDATE users_main SET Invites = Invites + 1 WHERE ID = ?", $userId);
            self::$cache->delete_value("u_$userId");
            $this->debug("Expired invite from user $userId", $userId);
            $this->processed++;
        }
        self::$db->commit();
    }
}
