<?php

namespace Gazelle;

class PM extends Base {
    protected const CACHE_KEY = 'pm_%d_%d';

    protected int $id;
    protected User $user;
    protected array $info = [];

    public function __construct(int $id, User $user) {
        parent::__construct();
        $this->id = $id;
        $this->user = $user;
    }

    public function id(): int {
        return $this->id;
    }

    public function flush() {
        $this->cache->delete_value(sprintf(self::CACHE_KEY, $this->id, $this->user->id()));
        $this->info = [];
    }

    public function info(): array {
        if (empty($this->info)) {
            $key = sprintf(self::CACHE_KEY, $this->id, $this->user->id());
            $info = $this->cache->get_value($key);
            if ($info === false) {
                $info = $this->db->rowAssoc("
                    SELECT c.Subject   AS subject,
                        cu.Sticky      AS pinned,
                        cu.UnRead      AS unread,
                        cu.ForwardedTo AS forwarded_to,
                        cu.SentDate    AS sent_date,
                        cu2.UserID     AS sender_id
                    FROM pm_conversations AS c
                    INNER JOIN pm_conversations_users cu ON (cu.ConvID = c.ID)
                    LEFT JOIN pm_conversations_users cu2 ON (cu2.ConvID = c.ID AND cu2.UserID != ?)
                    WHERE c.ID = ?
                        AND cu.UserID = ?
                    ", $this->user->id(), $this->id, $this->user->id()
                );
                foreach (['pinned', 'unread'] as $field) {
                    $info[$field] = ($info[$field] == '1');
                }

                // get the senders who have sent a message in this thread
                $this->db->prepared_query("
                    SELECT DISTINCT pm.SenderID
                    FROM pm_messages pm
                    WHERE pm.ConvID = ?
                    ", $this->id
                );
                $info['sender_list'] = $this->db->collect(0, false);

                // get the recipients of messages in this thread.
                $this->db->prepared_query("
                    SELECT DISTINCT cu.UserID
                    FROM pm_conversations_users cu
                    WHERE cu.ForwardedTo IN (0, cu.UserID)
                        AND cu.ConvID = ?
                        AND cu.UserID != ?
                    ", $this->id, $this->user->id()
                );
                $info['recipient_list'] = $this->db->collect(0, false);
                $this->cache->cache_value($key, $info, 86400);
                $info['from_cache'] = false;
            }
            $this->info = $info;

            $manager = new Manager\User;
            $this->info['forwarded_to'] = $manager->findById($this->info['forwarded_to'] ?? 0);

            $this->info['sender'] = [];
            foreach ($this->info['sender_list'] as $userId) {
                $this->info['sender'][$userId] = $manager->findById($userId);
            }

            // If the viewer has lost PM privileges, non-Staff recipients are filtered out
            $this->info['recipient'] = [];
            $pmRevoked = $this->user->disablePm();
            foreach ($this->info['recipient_list'] as $userId) {
                $recipient = $manager->findById($userId);
                if ($pmRevoked && !$recipient->isStaff()) {
                    continue;
                }
                $this->info['recipient'][] = $userId;
            }
        }
        return $this->info;
    }

    public function forwardedTo(): ?User {
        return $this->info()['forwarded_to'];
    }

    public function isPinned(): bool {
        return $this->info()['pinned'];
    }

    public function isUnread(): bool {
        return $this->info()['unread'];
    }

    public function sentDate(): string {
        return $this->info()['sent_date'];
    }

    public function subject(): string {
        return $this->info()['subject'];
    }

    public function body(): string {
        return $this->info()['body'];
    }

    public function recipientList(): array {
        return $this->info()['recipient'];
    }

    public function senderId(): ?int {
        return $this->info()['sender_id'];
    }

    public function senderList(): array {
        return $this->info()['sender'];
    }

    public function markRead(): int {
        $affected = 0;
        if ($this->isUnread()) {
            $this->db->prepared_query("
                UPDATE pm_conversations_users SET
                    UnRead = '0'
                WHERE UnRead = '1'
                    AND ConvID = ?
                    AND UserID = ?
                ", $this->id, $this->user->id()
            );
            $affected = $this->db->affected_rows();
            if ($affected) {
                $this->cache->decrement("inbox_new_" . $this->user->id());
                $this->flush();
            }
        }
        return $affected;
    }

    public function setForwardedTo(int $userId): int {
        $this->db->begin_transaction();
        $this->db->prepared_query("
            INSERT IGNORE INTO pm_conversations_users
                   (UserID, ConvID, InInbox, InSentbox, ReceivedDate)
            VALUES (?,      ?,      '1',     '0',       now())
            ON DUPLICATE KEY UPDATE
                ForwardedTo = 0,
                UnRead = 1
            ", $userId, $this->id
        );
        $affected = $this->db->affected_rows();
        $this->db->prepared_query("
            UPDATE pm_conversations_users SET
                ForwardedTo = ?
            WHERE ConvID = ?
                AND UserID = ?
            ", $userId, $this->user->id(), $this->id
        );
        $affected += $this->db->affected_rows();
        $this->db->commit();
        $this->cache->delete_value("inbox_new_$userId");
        return $affected;
    }

    public function postTotal(): int {
        return $this->db->scalar("
            SELECT count(*) from pm_messages where ConvID = ?
            ", $this->id
        );
    }

    public function postList(int $limit, int $offset): array {
        $this->db->prepared_query("
            SELECT ID AS id,
                SentDate AS sent_date,
                SenderID AS sender_id,
                Body AS body
            FROM pm_messages
            WHERE ConvID = ?
            ORDER BY ID
            LIMIT ? OFFSET ?
            ", $this->id, $limit, $offset
        );
        return $this->db->to_array(false, MYSQLI_ASSOC, false);
    }
}
