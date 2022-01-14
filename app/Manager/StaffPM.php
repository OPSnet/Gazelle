<?php

namespace Gazelle\Manager;

class StaffPM extends \Gazelle\Base {

    protected const ID_KEY = 'zz_spm_%d';

    public function findById(int $pmId): ?\Gazelle\StaffPM {
        $key = sprintf(self::ID_KEY, $pmId);
        $id = self::$cache->get_value($key);
        if ($id === false) {
            $id = self::$db->scalar("
                SELECT ID FROM staff_pm_conversations WHERE ID = ?
                ", $pmId
            );
            if (!is_null($id)) {
                self::$cache->cache_value($key, $id, 0);
            }
        }
        return $id ? new \Gazelle\StaffPM($id) : null;
    }

    public function findAllByUserId(int $userId): array {
        self::$db->prepared_query("
            SELECT ID
            FROM staff_pm_conversations
            WHERE UserID = ?
            ORDER BY Status, Date DESC
            ", $userId
        );
        $result = [];
        $list   = self::$db->collect(0, false);
        foreach ($list as $id) {
            $spm = $this->findById($id);
            if ($spm) {
                $result[] = $spm;
            }
        }
        return $result;
    }

    public function createCommonAnswer(string $name, string $message): int {
        self::$db->prepared_query("
            INSERT INTO staff_pm_responses
                   (Name, Message)
            VALUES (?,    ?)
            ", $name, $message
        );
        return self::$db->inserted_id();
    }

    public function modifyCommonAnswer(int $id, string $name, string $message): int {
        self::$db->prepared_query("
            UPDATE staff_pm_responses SET
                Name = ?,
                Message = ?
            WHERE ID = ?
            ", $name, $message, $id
        );
        return self::$db->affected_rows();
    }

    public function removeCommonAnswer(int $id): int {
        self::$db->prepared_query("
            DELETE FROM staff_pm_responses WHERE ID = ?
            ", $id
        );
        return self::$db->affected_rows();
    }

    public function commonAnswer(int $id): string {
        return (string)self::$db->scalar("
            SELECT Message FROM staff_pm_responses WHERE ID = ?
            ", $id
        );
    }

    public function commonAnswerList(): array {
        self::$db->prepared_query("
            SELECT ID   AS id,
                Name    AS name,
                Message AS message
            FROM staff_pm_responses
            ORDER BY Name
        ");
        return self::$db->to_array('id', MYSQLI_ASSOC, false);
    }

    public function countByStatus(\Gazelle\User $viewer, array $status): int {
        return self::$db->scalar("
            SELECT count(*) FROM staff_pm_conversations
            WHERE (Level <= ? OR AssignedToUser = ?)
                AND Status IN (" . placeholders($status) . ")
            ", $viewer->effectiveClass(), $viewer->id(), ...$status
        );
    }

    public function countAtLevel(\Gazelle\User $viewer, array $status): int {
        return self::$db->scalar("
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
