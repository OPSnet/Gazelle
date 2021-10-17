<?php

namespace Gazelle;

class Inbox extends Base {

    protected User $user;
    protected bool $unreadFirst;
    protected string $filter;
    protected string $folder = 'inbox';
    protected string $searchField = 'user';
    protected string $searchTerm;

    public function __construct(User $user) {
        parent::__construct();
        $this->user = $user;
    }

    public function setFilter(string $filter) {
        $this->filter = $filter;
        return $this;
    }

    public function setFolder(string $folder) {
        $this->folder = $folder;
        return $this;
    }

    public function setSearchField(string $searchField) {
        $this->searchField = $searchField;
        return $this;
    }

    public function setSearchTerm(string $searchTerm) {
        $this->searchTerm = $searchTerm;
        return $this;
    }

    public function setUnreadFirst(bool $unreadFirst) {
        $this->unreadFirst = $unreadFirst;
        return $this;
    }

    public function searchField(): string {
        return $this->searchField;
    }

    public function searchTerm(): ?string {
        return isset($this->searchTerm) ? $this->searchTerm : null;
    }

    public function showUnreadFirst(): bool {
        if (isset($this->unreadFirst)) {
            return $this->unreadFirst;
        }
        return (bool)$this->user->option('ListUnreadPMsFirst');
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
        $params = http_build_query($param);
        return 'inbox.php' . $params ? "?{$params}" : '';
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
            switch($this->searchField) {
                case 'subject':
                    $cond[] = "c.Subject LIKE concat('%', ?, '%')";
                    break;
                case 'user':
                    $cond[] = 'um.Username = ?';
                    break;
                case 'message':
                    $cond[] = "pm.Body LIKE concat('%', ?, '%')";
                    break;
                default:
                    throw new \UnexpectedValueException($this->searchField);
                    break;
            }
            $args[] = $this->searchTerm;
        }
        if (isset($this->filter)) {
            switch($this->filter) {
                case 'all':
                    break;
                case 'system':
                    $cond[] = "cu2.UserID IS NULL";
                    break;
                case 'user':
                    $cond[] = "cu2.UserID IS NOT NULL";
                    break;
                default:
                    throw new \UnexpectedValueException($this->filter);
                    break;
            }
        }
        return [$cond, $args];
    }

    public function messageTotal(): int {
        [$cond, $args] = $this->configure();
        $where = $cond ? ("WHERE " . implode(' AND ', $cond)) : '/* all */';
        return $this->db->scalar("
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

    public function messageList(int $limit, int $offset): array {
        [$cond, $args] = $this->configure();
        $unreadFirst = $this->showUnreadFirst() ? "cu.Unread," : '';
        array_push($args, $limit, $offset);
        $this->db->prepared_query("
            SELECT cu.ConvID
            FROM pm_conversations AS c
            INNER JOIN pm_conversations_users AS cu ON (cu.ConvID = c.ID AND cu.UserID = ?)
            INNER JOIN pm_messages AS pm USING (ConvID)
            LEFT JOIN pm_conversations_users AS cu2 ON (cu2.ConvID = c.ID AND cu2.UserID != ? AND cu2.ForwardedTo = 0)
            LEFT JOIN users_main AS um ON (um.ID = cu2.UserID)
            WHERE " . implode(' AND ', $cond) . "
            GROUP BY c.ID
            ORDER BY $unreadFirst cu.Sticky, cu.SentDate DESC
            LIMIT ? OFFSET ?
            ", $this->user->id(), $this->user->id(), ...$args
        );
        $list = $this->db->collect(0, false);
        $pmMan = new Manager\PM($this->user);
        $result = [];
        foreach ($list as $id) {
            $result[] = $pmMan->findById($id);
        }
        return $result;
    }

    public function massRemove(array $ids): int {
        $this->db->prepared_query("
            UPDATE pm_conversations_users SET
                InInbox   = '0',
                InSentbox = '0',
                Sticky    = '0',
                UnRead    = '0'
            WHERE UserID = ?
                AND ConvID IN (" . placeholders($ids) . ")
            ", $this->user->id(), ...$ids
        );
        $this->cache->deleteMulti(['inbox_new_' . $this->user->id(), ...array_map(fn ($id) => "pm_$id", $ids)]);
        return $this->db->affected_rows();
    }

    protected function massToggleRead(array $ids, string $value): int {
        $this->db->prepared_query("
            UPDATE pm_conversations_users SET
                Unread = ?
            WHERE UserID = ?
                AND ConvID IN (" . placeholders($ids) . ")
            ", $value, $this->user->id(), ...$ids
        );
        $this->cache->deleteMulti(['inbox_new_' . $this->user->id(), ...array_map(fn ($id) => "pm_$id", $ids)]);
        return $this->db->affected_rows();
    }

    public function massRead(array $ids): int {
        return $this->massToggleRead($ids, '0');
    }

    public function massUnread(array $ids): int {
        return $this->massToggleRead($ids, '1');
    }

    public function massTogglePinned(array $ids): int {
        $this->db->prepared_query("
            UPDATE pm_conversations_users SET
                Sticky = CASE WHEN Sticky = '0' THEN '1' ELSE '0' END
            WHERE UserID = ?
                AND ConvID IN (" . placeholders($ids) . ")
            ", $this->user->id(), ...$ids
        );
        $this->cache->deleteMulti(['inbox_new_' . $this->user->id(), ...array_map(fn ($id) => "pm_$id", $ids)]);
        return $this->db->affected_rows();
    }
}
