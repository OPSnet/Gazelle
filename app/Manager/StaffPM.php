<?php

namespace Gazelle\Manager;

class StaffPM extends \Gazelle\Base {

    public function findById (int $id) {
        $staffPM = $this->db->scalar("
            SELECT ID FROM staff_pm_conversations WHERE ID = ?
            ", $id
        );
        return is_null($staffPM) ? null : new \Gazelle\StaffPM($staffPM);
    }

    public function commonAnswerList(): array {
        $this->db->prepared_query("
            SELECT ID,
                Name
            FROM staff_pm_responses
            ORDER BY Name
        ");
        return $this->db->to_array('ID', MYSQLI_ASSOC, false);
    }

    public function heading(\Gazelle\User $viewer): array {
        if (!$viewer->isStaffPMReader()) {
            return [];
        }
        $heading = [
            [
                'count' => $this->db->scalar("
                    SELECT count(*) FROM staff_pm_conversations
                    WHERE Status IN ('Unanswered')
                        AND (Level <= ? OR AssignedToUser = ?)
                    ", $viewer->primaryClass(), $viewer->id()
                ),
                'link'  => "?view=unanswered",
                'title' => "All unanswered",
            ],
            [
                'count' => $this->db->scalar("
                    SELECT count(*) FROM staff_pm_conversations
                    WHERE Status IN ('Open', 'Unanswered')
                        AND (Level <= ? OR AssignedToUser = ?)
                    ", $viewer->primaryClass(), $viewer->id()
                ),
                'title' => "Unresolved",
                'link'  => "?view=open",
            ],
            [
                'title' => "Resolved",
                'link'  => "?view=resolved",
            ],
        ];
        if ($viewer->isStaff()) {
            $heading[] = [
                'title' => "Your unanswered",
                'view'  => "",
            ];
        }
        return $heading;
    }

    public function flsList() {
        if (($list = $this->cache->get_value('idfls')) === false) {
            $this->db->prepared_query("
                SELECT um.ID
                FROM users_main AS um
                INNER JOIN users_levels AS ul ON (ul.UserID = um.ID)
                WHERE ul.PermissionID = ?
                ORDER BY um.Username
                ", FLS_TEAM
            );
            $list = $this->db->collect(0);
            $this->cache->cache_value('idfls', $list, 3600);
        }
        $userMan = new \Gazelle\Manager\User;
        return array_map(function ($id) use ($userMan) { return $userMan->findById($id); }, $list);
    }

    public function staffList() {
        if (($staff = $this->cache->get_value('idstaff')) === false) {
            $this->db->prepared_query("
                SELECT sg.Name as staffGroup,
                    um.ID
                FROM users_main AS um
                INNER JOIN permissions AS p ON (p.ID = um.PermissionID)
                INNER JOIN staff_groups AS sg ON (sg.ID = p.StaffGroup)
                WHERE p.DisplayStaff = '1'
                    AND p.Secondary = 0
                ORDER BY sg.Sort, p.Level, um.Username
            ");
            $list = $this->db->to_array(false, MYSQLI_ASSOC);
            $staff = [];
            foreach ($list as $user) {
                if (!isset($staff[$user['staffGroup']])) {
                    $staff[$user['staffGroup']] = [];
                }
                $staff[$user['staffGroup']][] = $user['ID'];
            }
            $this->cache->cache_value('idstaff', $staff, 3600);
        }
        $userMan = new \Gazelle\Manager\User;
        foreach ($staff as &$group) {
            $group = array_map(function ($userId) use ($userMan) { return $userMan->findById($userId); }, $group);
        }
        return $staff;
    }
}
