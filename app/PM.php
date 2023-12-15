<?php

namespace Gazelle;

/**
 * A PM object is created from an inbox, either from a user with
 * User\Inbox::create(), or a system PM via User\Inbox::createSystem()
 *
 * Instantiating a PM object afterwards necessarily requires a user
 * object. This is the simplest, more secure method to ensure some
 * random user cannot access another PM object by accident.
 */

class PM extends Base {
    public final const CACHE_KEY = 'pm2_%d_%d';

    protected array|null $info;

    public function __construct(
        protected int $id,
        protected User $user
    ) {}

    public function id(): int {
        return $this->id;
    }

    public function flush(): static {
        self::$cache->delete_value(sprintf(self::CACHE_KEY, $this->id, $this->user->id()));
        unset($this->info);
        return $this;
    }

    public function info(): array {
        if (isset($this->info)) {
            return $this->info;
        }
        $key = sprintf(self::CACHE_KEY, $this->id, $this->user->id());
        $info = self::$cache->get_value($key);
        if ($info === false) {
            $info = self::$db->rowAssoc("
                SELECT c.Subject   AS subject,
                    cu.Sticky      AS pinned,
                    cu.UnRead      AS unread,
                    cu.ForwardedTo AS forwarded_to,
                    greatest(cu.ReceivedDate, cu.SentDate) AS created_date,
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
            self::$db->prepared_query("
                SELECT DISTINCT pm.SenderID
                FROM pm_messages pm
                WHERE pm.ConvID = ?
                ", $this->id
            );
            $info['sender_list'] = self::$db->collect(0, false);

            // get the recipients of messages in this thread.
            self::$db->prepared_query("
                SELECT DISTINCT cu.UserID
                FROM pm_conversations_users cu
                WHERE cu.ForwardedTo IN (0, cu.UserID)
                    AND cu.ConvID = ?
                    AND cu.UserID != ?
                ", $this->id, $this->user->id()
            );
            $info['recipient_list'] = [$this->user->id(), ...self::$db->collect(0, false)];
            self::$cache->cache_value($key, $info, 86400);
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
        return $this->info()['created_date'];
    }

    public function subject(): string {
        return $this->info()['subject'];
    }

    public function isReadable(): bool {
        return in_array($this->user->id(), $this->info()['sender_list'])
            || in_array($this->user->id(), $this->recipientList());
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
            self::$db->prepared_query("
                UPDATE pm_conversations_users SET
                    UnRead = '0'
                WHERE UnRead = '1'
                    AND ConvID = ?
                    AND UserID = ?
                ", $this->id, $this->user->id()
            );
            $affected = self::$db->affected_rows();
            if ($affected) {
                self::$cache->decrement("inbox_new_" . $this->user->id());
                $this->flush();
            }
        }
        return $affected;
    }

    public function markUnread(): int {
        self::$db->prepared_query("
            UPDATE pm_conversations_users SET
                Unread = '1'
            WHERE Unread = '0'
                AND ConvID = ?
                AND UserID = ?
            ", $this->id, $this->user->id()
        );
        $affected = self::$db->affected_rows();
        if ($affected > 0) {
            self::$cache->increment('inbox_new_' . $this->user->id());
            $this->flush();
        }
        return $affected;
    }

    public function pin(bool $pin): int {
        self::$db->prepared_query("
            UPDATE pm_conversations_users SET
                Sticky = ?
            WHERE ConvID = ?
                AND UserID = ?
            ", $pin ? '1' : '0', $this->id, $this->user->id()
        );
        $this->flush();
        return self::$db->affected_rows();
    }

    public function remove(): int {
        self::$db->prepared_query("
            UPDATE pm_conversations_users SET
                InInbox   = '0',
                InSentbox = '0',
                Sticky    = '0'
            WHERE ConvID = ?
                AND UserID = ?
            ", $this->id, $this->user->id()
        );
        $this->flush();
        return self::$db->affected_rows();
    }

    public function setForwardedTo(int $userId): int {
        self::$db->begin_transaction();
        self::$db->prepared_query("
            INSERT IGNORE INTO pm_conversations_users
                   (UserID, ConvID, InInbox, InSentbox, ReceivedDate)
            VALUES (?,      ?,      '1',     '0',       now())
            ON DUPLICATE KEY UPDATE
                ForwardedTo = 0,
                UnRead = 1
            ", $userId, $this->id
        );
        $affected = self::$db->affected_rows();
        self::$db->prepared_query("
            UPDATE pm_conversations_users SET
                ForwardedTo = ?
            WHERE ConvID = ?
                AND UserID = ?
            ", $userId, $this->user->id(), $this->id
        );
        $affected += self::$db->affected_rows();
        self::$db->commit();
        self::$cache->delete_value("inbox_new_$userId");
        return $affected;
    }

    public function postBody(int $postId): ?string {
        $body = self::$db->scalar("
            SELECT Body
            FROM pm_messages pm
            WHERE pm.ConvID = ?
                AND pm.ID = ?
            ", $this->id, $postId
        );
        return $body ? (string)$body : null;
    }

    public function postTotal(): int {
        return (int)self::$db->scalar("
            SELECT count(*) from pm_messages where ConvID = ?
            ", $this->id
        );
    }

    public function postList(int $limit, int $offset): array {
        self::$db->prepared_query("
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
        return self::$db->to_array(false, MYSQLI_ASSOC, false);
    }
}
