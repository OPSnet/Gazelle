<?php

namespace Gazelle;

class StaffPM extends BaseObject {
    protected $info;
    protected $author;
    protected $assigned;

    public function __construct(int $id) {
        parent::__construct($id);
        $this->info = $this->db->rowAssoc("
            SELECT Subject     AS subject,
                UserID         AS user_id,
                Level          AS class_level,
                AssignedToUser AS assigned_user_id,
                Unread         AS unread,
                Status         AS status
            FROM staff_pm_conversations
            WHERE ID = ?
            ", $this->id
        );
        $userMan = new Manager\User;
        $this->author = $userMan->findById($this->info['user_id'] ?? 0);
        $this->assigned = $userMan->findById($this->info['assigned_user_id'] ?? 0);
    }

    public function tableName(): string {
        return 'staff_pm_conversations';
    }

    public function flush() {
        // no-op
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

    public function isUnread(): bool {
        return (bool)$this->info['unread'];
    }

    public function subject(): string {
        return $this->info['subject'];
    }

    public function unassigned(): bool {
        return is_null($this->assigned);
    }

    public function inProgress(): bool {
        return $this->info['status'] != 'Resolved';
    }

    /**
     * Can the person logged in view this message?
     * - They created it
     * - They are FLS and it has not yet been assigned, or assigned to them
     * - They are Staff and the conversation is viewable at their class level
     *
     * @param Gazelle\User viewer
     * @return bool Can they see it?
     */
    public function visible(User $viewer): bool {
        return $viewer->id() === $this->author->id()
            || ($viewer->isFLS() && (is_null($this->assigned) || $this->assigned->id() === $viewer->id()))
            || ($viewer->isStaff() && $this->info['class_level'] <= $viewer->effectiveClass());
    }

    public function markAsRead(User $viewer): int {
        $this->db->prepared_query("
            UPDATE staff_pm_conversations SET
                Unread = false
            WHERE ID = ?
            ", $this->id
        );
        $this->cache->delete_value("staff_pm_new_" . $viewer->id());
        return $this->db->affected_rows();
    }

    public function thread(): array {
        $this->db->prepared_query("
            SELECT ID     AS id,
                UserID    AS user_id,
                SentDate  AS sent_date,
                Message   AS body
            FROM staff_pm_messages
            WHERE ConvID = ?
            ORDER BY SentDate
            ", $this->id
        );
        return $this->db->to_array(false, MYSQLI_ASSOC, false);
    }
}
