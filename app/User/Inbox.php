<?php

namespace Gazelle\User;

class Inbox extends \Gazelle\BaseUser {
    final public const tableName = 'pm_conversations_users';

    final protected const CACHE_NEW = 'inbox_new_%d';

    protected bool $unreadFirst;
    protected string $filter;
    protected string $folder = 'inbox';
    protected string $searchField = 'user';
    protected string $searchTerm;

    public function __construct(\Gazelle\User $user) {
        parent::__construct($user);
    }

    public function flush(): static  {
        self::$cache->delete_value(sprintf(self::CACHE_NEW, $this->id()));
        $this->user->flush();
        return $this;
    }

    public function createSystem(string $subject, string $body): ?\Gazelle\PM {
        return $this->create(null, $subject, $body);
    }

    /**
     * To send a message to a user, you instantiate their inbox and
     * create() a PM
     */
    public function create(?\Gazelle\User $from, string $subject, string $body): ?\Gazelle\PM {
        $fromId = $from?->id() ?? 0;
        if ($this->id() === $fromId) {
            // Don't allow users to send messages to the system or themselves
            return null;
        }

        $qid = self::$db->get_query_id();
        self::$db->begin_transaction();
        self::$db->prepared_query("
            INSERT INTO pm_conversations (Subject) VALUES (?)
            ", mb_substr($subject, 0, 255)
        );
        $convId = self::$db->inserted_id();

        $placeholders = [
            "(?, ?, '1', '0', '1')",
            "(?, ?, '0', '1', '0')",
        ];
        $args = [$this->id(), $convId, $fromId, $convId];

        self::$db->prepared_query("
            INSERT INTO pm_conversations_users
                   (UserID, ConvID, InInbox, InSentbox, UnRead)
            VALUES
            " . implode(', ', $placeholders), ...$args
        );
        self::$db->prepared_query("
            INSERT INTO pm_messages
                   (SenderID, ConvID, Body)
            VALUES (?,        ?,      ?)
            ", $fromId, $convId, $body
        );
        self::$db->commit();
        self::$db->set_query_id($qid);

        $senderName = $from?->username() ?? 'System';
        (new \Gazelle\Manager\Notification)->push(
            [$this->id()],
            "Message from $senderName, Subject: $subject", $body, SITE_URL . '/inbox.php', \Gazelle\Manager\Notification::INBOX,
        );
        $this->flush();
        self::$cache->delete_multi([
            sprintf(\Gazelle\PM::CACHE_KEY, $convId, $fromId),
            sprintf(\Gazelle\PM::CACHE_KEY, $convId, $this->id()),
        ]);

        return new \Gazelle\PM($convId, $this->user);
    }

    public function setFilter(string $filter): static {
        $this->filter = $filter;
        return $this;
    }

    public function setFolder(string $folder): static {
        $this->folder = $folder;
        return $this;
    }

    public function setSearchField(string $searchField): static {
        $this->searchField = $searchField;
        return $this;
    }

    public function setSearchTerm(string $searchTerm): static {
        $this->searchTerm = $searchTerm;
        return $this;
    }

    public function setUnreadFirst(bool $unreadFirst): static {
        $this->unreadFirst = $unreadFirst;
        return $this;
    }

    public function searchField(): string {
        return $this->searchField;
    }

    public function searchTerm(): ?string {
        return $this->searchTerm ?? null;
    }

    public function showUnreadFirst(): bool {
        return $this->unreadFirst ?? (bool)$this->user->option('ListUnreadPMsFirst');
    }

    public function folder(): string {
        return $this->folder;
    }

    public function folderLink(string $folder, bool $unreadFirst): string {
        $param = [
            'sort' => $unreadFirst ? 'unread' : 'latest',
        ];
        if ($folder !== 'inbox') {
            $param['section'] = 'sentbox';
        }
        if (isset($this->filter)) {
            $param['filter'] = $this->filter;
        }
        if (isset($this->searchTerm) && isset($this->searchField)) {
            $param['search'] = $this->searchTerm;
            $param['searchtype'] = $this->searchField;
        }
        return 'inbox.php?' . http_build_query($param);
    }

    public function folderTitle($folder): string {
        return $folder == 'sentbox' ? 'Sentbox' : 'Inbox';
    }

    public function title(): string {
        return $this->folderTitle($this->folder);
    }

    public function folderList(): array {
        return ['inbox', 'sentbox'];
    }

    protected function configure(): array {
        $cond = [];
        $args = [];
        if ($this->folder() === 'sentbox') {
            $cond[] = "cu.InSentbox = '1'";
        } else {
            $cond[] = "cu.InInbox = '1'";
        }
        if (isset($this->searchField) && isset($this->searchTerm) && !empty($this->searchTerm)) {
            $cond[] = match ($this->searchField) {
                'subject' => "c.Subject LIKE concat('%', ?, '%')",
                'user'    => 'um.Username = ?',
                'message' => "pm.Body LIKE concat('%', ?, '%')",
                default   => '1 = 0',
            };
            $args[] = $this->searchTerm;
        }
        if (isset($this->filter)) {
            switch ($this->filter) {
                case 'system':
                    $cond[] = "cu2.UserID IS NULL";
                    break;
                case 'user':
                    $cond[] = "cu2.UserID IS NOT NULL";
                    break;
                case 'all':
                default:
                    break;
            }
        }
        return [$cond, $args];
    }

    public function unreadTotal(): int {
        $key = sprintf(self::CACHE_NEW, $this->id());
        $unread = self::$cache->get_value($key);
        if ($unread === false) {
            $unread = (int)self::$db->scalar("
                SELECT count(*)
                FROM pm_conversations_users
                WHERE UnRead    = '1'
                    AND InInbox = '1'
                    AND UserID  = ?
                ", $this->id()
            );
            self::$cache->cache_value($key, $unread, 0);
        }
        return $unread;
    }

    public function messageTotal(): int {
        [$cond, $args] = $this->configure();
        $where = $cond ? ("WHERE " . implode(' AND ', $cond)) : '/* all */';
        return (int)self::$db->scalar("
            SELECT count(DISTINCT cu.ConvID)
            FROM pm_conversations AS c
            INNER JOIN pm_conversations_users AS cu ON (cu.ConvID = c.ID AND cu.UserID = ?)
            INNER JOIN pm_messages AS pm USING (ConvID)
            LEFT JOIN pm_conversations_users AS cu2 ON (cu2.ConvID = c.ID AND cu2.UserID != ? AND cu2.ForwardedTo = 0)
            LEFT JOIN users_main AS um ON (um.ID = cu2.UserID)
            $where
            ", $this->user->id(), $this->user->id(), ...$args
        );
    }

    public function messageList(\Gazelle\Manager\PM $pmMan, int $limit, int $offset): array {
        [$cond, $args] = $this->configure();
        $unreadFirst = $this->showUnreadFirst() ? "if(cu.Unread = '1', 0, 1) ASC," : '';
        array_push($args, $limit, $offset);
        self::$db->prepared_query("
            SELECT DISTINCT cu.ConvID
            FROM pm_conversations AS c
            INNER JOIN pm_conversations_users AS cu ON (cu.ConvID = c.ID AND cu.UserID = ?)
            INNER JOIN pm_messages AS pm USING (ConvID)
            LEFT JOIN pm_conversations_users AS cu2 ON (cu2.ConvID = c.ID AND cu2.UserID != ? AND cu2.ForwardedTo = 0)
            LEFT JOIN users_main AS um ON (um.ID = cu2.UserID)
            WHERE " . implode(' AND ', $cond) . "
            ORDER BY cu.Sticky, $unreadFirst greatest(cu.ReceivedDate, cu.SentDate) DESC
            LIMIT ? OFFSET ?
            ", $this->user->id(), $this->user->id(), ...$args
        );
        return array_map(fn($id) => $pmMan->findById($id), self::$db->collect(0, false));
    }

    protected function massFlush(array $ids): void {
        $userId = $this->user->id();
        self::$cache->delete_multi([
            sprintf(self::CACHE_NEW, $userId),
            ...array_map(fn ($id) => sprintf(\Gazelle\PM::CACHE_KEY, $id, $userId), $ids)
        ]);
    }

    public function massRemove(array $ids): int {
        self::$db->prepared_query("
            UPDATE pm_conversations_users SET
                InInbox   = '0',
                InSentbox = '0',
                Sticky    = '0',
                UnRead    = '0'
            WHERE UserID = ?
                AND ConvID IN (" . placeholders($ids) . ")
            ", $this->user->id(), ...$ids
        );
        $this->massFlush($ids);
        return self::$db->affected_rows();
    }

    protected function massToggleRead(array $ids, string $value): int {
        self::$db->prepared_query("
            UPDATE pm_conversations_users SET
                Unread = ?
            WHERE UserID = ?
                AND ConvID IN (" . placeholders($ids) . ")
            ", $value, $this->user->id(), ...$ids
        );
        $this->massFlush($ids);
        return self::$db->affected_rows();
    }

    public function massRead(array $ids): int {
        return $this->massToggleRead($ids, '0');
    }

    public function massUnread(array $ids): int {
        return $this->massToggleRead($ids, '1');
    }

    public function massTogglePinned(array $ids): int {
        self::$db->prepared_query("
            UPDATE pm_conversations_users SET
                Sticky = CASE WHEN Sticky = '0' THEN '1' ELSE '0' END
            WHERE UserID = ?
                AND ConvID IN (" . placeholders($ids) . ")
            ", $this->user->id(), ...$ids
        );
        $this->massFlush($ids);
        return self::$db->affected_rows();
    }
}
