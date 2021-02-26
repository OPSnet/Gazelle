<?php

namespace Gazelle\Schedule\Tasks;

class ExpireInvites extends \Gazelle\Schedule\Task
{
    public function run()
    {
        $userQuery = $this->db->prepared_query("SELECT InviterID FROM invites WHERE Expires < now()");
        $this->db->prepared_query("DELETE FROM invites WHERE Expires < now()");

        $this->db->set_query_id($userQuery);
        $users = $this->db->collect('InviterID', false);
        foreach ($users as $user) {
            $this->db->prepared_query("UPDATE users_main SET Invites = Invites + 1 WHERE ID = ?", $user);
            $this->cache->deleteMulti(["u_$user", "user_info_heavy_$user"]);
            $this->debug("Expired invite from user $user", $user);
            $this->processed++;
        }
    }
}
