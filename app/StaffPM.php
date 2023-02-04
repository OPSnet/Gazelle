<?php

namespace Gazelle;

class StaffPM extends BaseObject {
    protected $author;
    protected $assigned;

    public function flush(): StaffPM {
        $this->info = [];
        return $this;
    }
    public function link(): string { return sprintf('<a href="%s">%s</a>', $this->url(), display_str($this->subject())); }
    public function location(): string { return 'staffpm.php?action=viewconv&id=' . $this->id; }
    public function tableName(): string { return 'staff_pm_conversations'; }

    public function flushUser(User $user) {
        self::$cache->delete_multi([
            "num_staff_pms_" . $user->id(),
            "staff_pm_new_" . $user->id(),
        ]);
    }

    public function info(): array {
        if (isset($this->info) && !empty($this->info)) {
            return $this->info;
        }
        $this->info = self::$db->rowAssoc("
            SELECT spm.Subject     AS subject,
                spm.UserID         AS user_id,
                spm.Level          AS class_level,
                coalesce(p.Name, concat('Level ', spm.Level))
                                   AS userclass_name,
                spm.AssignedToUser AS assigned_user_id,
                spm.Unread         AS unread,
                spm.Status         AS status,
                spm.Date           AS date
            FROM staff_pm_conversations spm
            LEFT JOIN permissions p USING (Level)
            WHERE spm.ID = ?
            ", $this->id
        );
        return $this->info;
    }

    public function assignedUserId(): int {
        return (int)$this->info()['assigned_user_id'];
    }

    public function classLevel(): int {
        return $this->info()['class_level'];
    }

    public function date(): string {
        return $this->info()['date'];
    }

    public function inProgress(): bool {
        return $this->info()['status'] !== 'Resolved';
    }

    public function isResolved(): bool {
        return $this->info()['status'] === 'Resolved';
    }

    public function isUnread(): bool {
        return (bool)$this->info()['unread'];
    }

    public function subject(): string {
        return $this->info()['subject'];
    }

    public function unassigned(): bool {
        return $this->assignedUserId() > 0;
    }

    public function userId(): int {
        return (int)$this->info()['user_id'];
    }

    public function userclassName(): string {
        return $this->info()['userclass_name'];
    }

    /**
     * Can the viewer view this message?
     * - They created it or it is assigned to them
     * - They are FLS and it has not yet been assigned
     * - They are Staff and the conversation is viewable at their class level
     *
     * @return bool Can they see it?
     */
    public function visible(User $viewer): bool {
        return in_array($viewer->id(), [$this->userId(), $this->assignedUserId()])
            || ($viewer->isFLS() && $this->classLevel() == 0)
            || ($viewer->isStaff() && $this->classLevel() <= $viewer->effectiveClass());
    }

    public function assign(User $to, User $by): int {
        self::$db->prepared_query("
            UPDATE staff_pm_conversations SET
                Status = 'Unanswered',
                AssignedToUser = ?,
                Level = ?
            WHERE ID = ?
            ", $to->id(), $to->effectiveClass(), $this->id,
        );
        $affected = self::$db->affected_rows();
        $this->flush();
        $this->flushUser($to);
        $this->flushUser($by);
        return $affected;
    }

    public function assignClass(int $level, User $viewer): int {
        self::$db->prepared_query("
            UPDATE staff_pm_conversations
            SET Status = 'Unanswered',
                Level = ?,
                AssignedToUser = NULL
            WHERE ID = ?"
            , $level, $this->id
        );
        $affected = self::$db->affected_rows();
        $this->flush();
        $this->flushUser($viewer);
        return $affected;
    }

    public function markAsRead(User $viewer): int {
        self::$db->prepared_query("
            UPDATE staff_pm_conversations SET
                Unread = false
            WHERE ID = ?
            ", $this->id
        );
        $affected = self::$db->affected_rows();
        $this->flush();
        $this->flushUser($viewer);
        return $affected;
    }

    public function reply(User $user, string $message): int {
        self::$db->begin_transaction();
        self::$db->prepared_query("
            INSERT INTO staff_pm_messages
                   (UserID, Message, ConvID)
            VALUES (?,      ?,       ?)
            ", $user->id(), $message, $this->id
        );
        $affected = self::$db->affected_rows();
        self::$db->prepared_query("
            UPDATE staff_pm_conversations SET
                Date = now(),
                Unread = true,
                Status = ?
            WHERE ID = ?
            ", $user->isStaffPMReader() ? 'Open' : 'Unanswered', $this->id
        );
        self::$db->commit();
        $this->flush();
        $this->flushUser($user);
        self::$cache->delete_value("staff_pm_new_{$this->userId()}");
        return $affected;
    }

    protected function modifyStatus(User $user, string $status, ?int $resolver): int {
        self::$db->prepared_query("
            UPDATE staff_pm_conversations SET
                Date   = now(),
                Status = ?,
                ResolverID = ?
            WHERE ID = ?
            ", $status, $resolver, $this->id
        );
        $affected = self::$db->affected_rows();
        $this->flush();
        $this->flushUser($user);
        return $affected;
    }

    public function resolve(User $user): int {
        return $this->modifyStatus($user, 'Resolved', $user->id());
    }

    public function unresolve(User $user): int {
        return $this->modifyStatus($user, 'Unanswered', null);
    }

    public function thread(): array {
        self::$db->prepared_query("
            SELECT ID    AS id,
                UserID   AS user_id,
                SentDate AS sent_date,
                Message  AS body
            FROM staff_pm_messages
            WHERE ConvID = ?
            ORDER BY SentDate
            ", $this->id
        );
        return self::$db->to_array(false, MYSQLI_ASSOC, false);
    }
}
