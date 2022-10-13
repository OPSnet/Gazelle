<?php

namespace Gazelle;

class StaffPM extends BaseObject {
    protected array $info;
    protected $author;
    protected $assigned;

    public function __construct(int $id) {
        parent::__construct($id);
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
        $userMan = new Manager\User;
        $this->author = $userMan->findById($this->info['user_id'] ?? 0);
        $this->assigned = $userMan->findById($this->info['assigned_user_id'] ?? 0);
    }

    public function tableName(): string {
        return 'staff_pm_conversations';
    }

    public function url(): string {
        return 'staffpm.php?action=viewconv&id=' . $this->id;
    }

    public function link(): string {
        return sprintf('<a href="%s">%s</a>', $this->url(), display_str($this->subject()));
    }

    public function flush() {
        // no-op
    }

    public function assignedUserId(): int {
        return (int)$this->info['assigned_user_id'];
    }

    public function assigned(): ?User {
        return $this->assigned;
    }

    public function author(): User {
        return $this->author;
    }

    public function classLevel(): int {
        return $this->info['class_level'];
    }

    public function date(): string {
        return $this->info['date'];
    }

    public function inProgress(): bool {
        return $this->info['status'] !== 'Resolved';
    }

    public function isReadable(User $user): bool {
        return (!$user->isStaffPMReader() && !in_array($user->id(), [$this->userId(), $this->assignedUserId()]))
            || ($user->isFLS() && !in_array($this->assignedUserId(), [0, $user->id()]))
            || ($user->isStaff() && $this->classLevel() > $user->effectiveClass());
    }

    public function isResolved(): bool {
        return $this->info['status'] === 'Resolved';
    }

    public function isUnread(): bool {
        return (bool)$this->info['unread'];
    }

    public function subject(): string {
        return $this->info['subject'];
    }

    public function unassigned(): bool {
        return is_null($this->assigned);
    }

    public function userId(): int {
        return (int)$this->info['user_id'];
    }

    public function userclassName(): string {
        return $this->info['userclass_name'];
    }

    /**
     * Can the person logged in view this message?
     * - They created it
     * - They are FLS and it has not yet been assigned, or assigned to them
     * - They are Staff and the conversation is viewable at their class level
     *
     * @return bool Can they see it?
     */
    public function visible(User $viewer): bool {
        return $viewer->id() === $this->author->id()
            || ($viewer->isFLS() && (is_null($this->assigned) || $this->assigned->id() === $viewer->id()))
            || ($viewer->isStaff() && $this->info['class_level'] <= $viewer->effectiveClass());
    }

    public function markAsRead(User $viewer): int {
        self::$db->prepared_query("
            UPDATE staff_pm_conversations SET
                Unread = false
            WHERE ID = ?
            ", $this->id
        );
        self::$cache->delete_value("staff_pm_new_" . $viewer->id());
        return self::$db->affected_rows();
    }

    public function unresolve(): int {
        self::$db->prepared_query("
            UPDATE staff_pm_conversations SET
                Date = now(),
                Status = 'Unanswered'
            WHERE ID = ?
            ", $this->id
        );
        return self::$db->affected_rows();
    }

    public function thread(): array {
        self::$db->prepared_query("
            SELECT ID     AS id,
                UserID    AS user_id,
                SentDate  AS sent_date,
                Message   AS body
            FROM staff_pm_messages
            WHERE ConvID = ?
            ORDER BY SentDate
            ", $this->id
        );
        return self::$db->to_array(false, MYSQLI_ASSOC, false);
    }
}
