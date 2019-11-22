<?php

namespace Gazelle\Schedule\Tasks;

class ExpireInvites extends \Gazelle\Schedule\Task
{
    public function run()
    {
        $userQuery = $this->db->prepared_query("SELECT InviterID FROM invites WHERE Expires < now()");
        $this->db->prepared_query("DELETE FROM invites WHERE Expires < now()");

        $this->db->set_query_id($userQuery);
        $users = $this->db->to_array();
        foreach ($users as $user) {
            list($userID) = $user;
            $this->db->prepared_query("UPDATE users_main SET Invites = Invites + 1 WHERE ID = ?", $userID);
            $this->cache->begin_transaction("user_info_heavy_$userID");
            $this->cache->update_row(false, ['Invites' => '+1']);
            $this->cache->commit_transaction(0);

            $this->processed++;
            $this->debug("Expired invite from user $userID", $userID);
        }
    }
}
