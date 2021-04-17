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
}
