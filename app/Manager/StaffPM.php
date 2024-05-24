<?php

namespace Gazelle\Manager;

class StaffPM extends \Gazelle\BaseManager {
    protected const ID_KEY = 'zz_spm_%d';

    protected array $args;
    protected array $cond;

    public function create(\Gazelle\User $user, int $level, string $subject, string $message): \Gazelle\StaffPM {
        self::$db->begin_transaction();
        self::$db->prepared_query("
            INSERT INTO staff_pm_conversations
                   (UserID, Level, Subject)
            VALUES (?,      ?,     ?)
            ", $user->id(), $level, $subject
        );
        $convId = self::$db->inserted_id();
        self::$db->prepared_query("
            INSERT INTO staff_pm_messages
                   (UserID, Message, ConvID)
            VALUES (?,      ?,       ?)
            ", $user->id(), $message, $convId
        );
        self::$db->commit();
        return $this->findById($convId);
    }

    public function findById(int $pmId): ?\Gazelle\StaffPM {
        $key = sprintf(self::ID_KEY, $pmId);
        $id = self::$cache->get_value($key);
        if ($id === false) {
            $id = self::$db->scalar("
                SELECT ID FROM staff_pm_conversations WHERE ID = ?
                ", $pmId
            );
            if (!is_null($id)) {
                self::$cache->cache_value($key, $id, 7200);
            }
        }
        return $id ? new \Gazelle\StaffPM($id) : null;
    }

    public function findAllByUser(\Gazelle\User $user): array {
        self::$db->prepared_query("
            SELECT ID
            FROM staff_pm_conversations
            WHERE UserID = ?
            ORDER BY Status, Date DESC
            ", $user->id()
        );
        $result = [];
        foreach (self::$db->collect(0, false) as $id) {
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

    public function commonAnswer(int $id): ?string {
        $answer = self::$db->scalar("
            SELECT Message FROM staff_pm_responses WHERE ID = ?
            ", $id
        );
        return $answer ? (string)$answer : null;
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
        return (int)self::$db->scalar("
            SELECT count(*) FROM staff_pm_conversations
            WHERE (Level <= ? OR AssignedToUser = ?)
                AND Status IN (" . placeholders($status) . ")
            ", $viewer->privilege()->effectiveClassLevel(), $viewer->id(), ...$status
        );
    }

    public function countAtLevel(\Gazelle\User $viewer, array $status): int {
        return (int)self::$db->scalar("
            SELECT count(*) FROM staff_pm_conversations
            WHERE (Level = ? OR AssignedToUser = ?)
                AND Status IN (" . placeholders($status) . ")
            ", $viewer->privilege()->effectiveClassLevel(), $viewer->id(), ...$status
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

    public function history(bool $isStaffView, User $userMan, int $classLevel, array $userIds, int $interval): array {
        $list = $isStaffView
            ? $this->staffHistory($classLevel, $userIds, $interval)
            : $this->userHistory($classLevel, $userIds, $interval);
        foreach ($list as &$row) {
            $row['user'] = $userMan->findById($row['user_id']);
        }
        unset($row);
        return $list;
    }

    public function staffHistory(int $classLevel, array $userIds, int $interval): array {
        self::$db->prepared_query("
            SELECT um.ID as user_id,
                count(distinct spm.ID) AS total,
                count(distinct spc.ID) AS total2
            FROM users_main um
            INNER JOIN permissions           p   ON (p.ID = um.PermissionID)
            INNER JOIN staff_pm_messages     spm ON (spm.UserID = um.ID)
            LEFT JOIN staff_pm_conversations spc ON (spc.ResolverID = um.ID AND spc.Status = 'Resolved' AND spc.Date > now() - INTERVAL ? DAY)
            WHERE spm.SentDate > now() - INTERVAL ? DAY
                AND p.Level <= ?
                AND um.ID IN (" . placeholders($userIds) . ")
            GROUP BY um.ID
            ORDER BY total DESC, total2 DESC
        ", $interval, $interval, $classLevel, ...$userIds);
        return self::$db->to_array(false, MYSQLI_ASSOC, false);
    }

    public function userHistory(int $classLevel, array $userIds, int $interval): array {
        self::$db->prepared_query("
            SELECT um.ID as user_id,
                count(distinct spm.ID) AS total,
                count(distinct spc.ID) AS total2
            FROM users_main um
            INNER JOIN permissions           p   ON (p.ID = um.PermissionID)
            INNER JOIN staff_pm_messages     spm ON (spm.UserID = um.ID)
            LEFT JOIN staff_pm_conversations spc ON (spc.UserID = um.ID AND spc.Date > now() - INTERVAL ? DAY)
            WHERE spm.SentDate > now() - INTERVAL ? DAY
                AND p.Level <= ?
                AND um.ID NOT IN (" . placeholders($userIds) . ")
            GROUP BY um.ID
            ORDER BY total DESC, total2 DESC
        ", $interval, $interval, $classLevel, ...$userIds);
        return self::$db->to_array(false, MYSQLI_ASSOC, false);
    }

    public function setSearchId(\Gazelle\User $user, int $id): static {
        $this->cond = ['spc.Level <= ? AND spc.UserID = ? AND spc.status = ?'];
        $this->args = [$user->privilege()->effectiveClassLevel(), $id, 'Resolved'];
        return $this;
    }

    public function setSearchStatusList(\Gazelle\User $user, array $status): static {
        $this->cond = ['(spc.Level <= ? OR spc.AssignedToUser = ?) AND spc.Status IN (' . placeholders($status) . ')'];
        $this->args = [$user->privilege()->effectiveClassLevel(), $user->id(), ...$status];
        return $this;
    }

    public function setUserclassLevel(int $level): static {
        $this->cond[] = 'spc.Level >= ?';
        $this->args[] = $level;
        return $this;
    }

    public function searchTotalSql(): string {
        $where = implode(' AND ', $this->cond);
        return "SELECT count(*) FROM staff_pm_conversations AS spc WHERE $where";
    }

    public function searchTotal(): int {
        return (int)self::$db->scalar($this->searchTotalSql(), ...$this->args);
    }

    public function pageSql(): string {
        $where = implode(' AND ', $this->cond);
        return "
            SELECT spc.ID          AS id,
                spc.Subject        AS subject,
                spc.UserID         AS user_id,
                spc.AssignedToUser AS assigned_user_id,
                spc.ResolverID     AS resolver_user_id,
                spc.Status         AS status,
                spc.Date           AS created,
                spc.Unread         AS is_unread,
                spc.Level          AS userclass_level,
                concat(
                    coalesce(p.Name, 'First Line Support'),
                    if(p.Level IS NULL OR p.Level = 1000 OR p.Secondary = 1, '', '+')
                )                  AS userclass,
                greatest(0, count(spm.ID) - 1)
                                   AS reply_total,
                (
                    SELECT spmu.UserID
                    FROM staff_pm_messages spmu
                    WHERE spmu.ID = (
                        SELECT max(last.ID)
                        FROM staff_pm_messages last
                        WHERE last.ConvID = spm.ConvID
                    )
                ) as last_user_id
            FROM staff_pm_conversations spc
            LEFT JOIN permissions p ON (p.Level = spc.Level)
            LEFT JOIN staff_pm_messages spm ON (spm.ConvID = spc.ID)
            WHERE $where
            GROUP BY spc.ID
            ORDER BY IF(AssignedToUser = ?, 0, 1) ASC, spc.Date DESC
            LIMIT ? OFFSET ?
            ";
    }

    public function page(\Gazelle\User $user, int $limit, int $offset): array {
        self::$db->prepared_query(
            $this->pageSql(),
            ...[...$this->args, $user->id(), $limit, $offset]
        );
        return self::$db->to_array(false, MYSQLI_ASSOC, false);
    }
}
