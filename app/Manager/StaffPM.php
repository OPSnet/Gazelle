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

    public function countByStatus(\Gazelle\User $viewer, array $status): int {
        return $this->db->scalar("
            SELECT count(*) FROM staff_pm_conversations
            WHERE (Level <= ? OR AssignedToUser = ?)
                AND Status IN (" . placeholders($status) . ")
            ", $viewer->effectiveClass(), $viewer->id(), ...$status
        );
    }

    public function countAtLevel(\Gazelle\User $viewer, array $status): int {
        return $this->db->scalar("
            SELECT count(*) FROM staff_pm_conversations
            WHERE (Level = ? OR AssignedToUser = ?)
                AND Status IN (" . placeholders($status) . ")
            ", $viewer->effectiveClass(), $viewer->id(), ...$status
        );
    }

    public function heading(\Gazelle\User $viewer): array {
        if (!$viewer->isStaffPMReader()) {
            return [];
        }
        $heading = [
            [
                'count' => $this->countByStatus($viewer, ['Unanswered']),
                'title' => "Unanswered",
                'link'  => "",
            ],
            [
                'count' => $this->countByStatus($viewer, ['Open']),
                'title' => "Waiting for reply",
                'link'  => "?view=open",
            ],
            [
                'title' => "Resolved",
                'link'  => "?view=resolved",
            ],
        ];
        if ($viewer->isStaff()) {
            $heading = array_merge([
                'unanswered' => [
                    'count'  => $this->countByStatus($viewer, ['Unanswered']),
                    'status' => ['Unanswered'],
                    'title'  => 'All unanswered',
                    'view'   => 'Unanswered',
                ]],
                $heading
            );
        }
        return $heading;
    }
}
