<?php

namespace Gazelle\Schedule\Tasks;

class ExpireInvites extends \Gazelle\Schedule\Task
{
    public function run()
    {
        $this->db->begin_transaction();
        $this->db->prepared_query("SELECT InviterID FROM invites WHERE Expires < now()");
        $users = $this->db->collect('InviterID', false);

        $this->db->prepared_query("DELETE FROM invites WHERE Expires < now()");
        $this->db->prepared_query("
            DELETE isp FROM invite_source_pending isp
            LEFT JOIN invites i ON (i.InviteKey = isp.invite_key)
            WHERE i.InviteKey IS NULL
        ");

        foreach ($users as $user) {
            $this->db->prepared_query("UPDATE users_main SET Invites = Invites + 1 WHERE ID = ?", $user);
            $this->cache->deleteMulti(["u_$user", "user_info_heavy_$user"]);
            $this->debug("Expired invite from user $user", $user);
            $this->processed++;
        }
        $this->db->commit();
    }
}
