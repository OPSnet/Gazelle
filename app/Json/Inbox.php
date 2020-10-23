<?php

namespace Gazelle\Json;

class Inbox extends \Gazelle\Json {

    protected $dateColumn;
    protected $unreadFirst = false;
    protected $userId;

    protected $cond = [];
    protected $args = [];
    protected $join = [];

    public function __construct() {
        parent::__construct();
    }

    public function setViewer(int $userId) {
        $this->userId = $userId;
        return $this;
    }

    public function setUnreadFirst(bool $unreadFirst) {
        $this->unreadFirst = $unreadFirst;
        return $this;
    }

    public function setFolder(string $folder) {
        switch ($folder) {
            case 'inbox':
                $this->dateColumn = 'cu.ReceivedDate';
                $this->cond[] = "cu.InInbox = '1'";
                break;
            case 'sentbox':
                $this->dateColumn = 'cu.SentDate';
                $this->cond[] = "cu.InSentbox = '1'";
                break;
            default:
                $this->failure('bad folder');
                break;
        }
        return $this;
    }

    public function setSearch(string $searchType, string $search) {
        $search = trim($search);
        switch($searchType) {
            case 'subject':
                $words = array_unique(array_map('trim', explode(' ', $search)));
                $this->cond = array_merge($this->cond, array_fill(0, count($words), "c.Subject LIKE concat('%', ?, '%')"));
                $this->args = array_merge($this->args, $words);
                break;
            case 'message':
                $this->join[] = 'INNER JOIN pm_messages AS m ON (c.ID = m.ConvID)';
                $words = array_unique(array_map('trim', explode(' ', $search)));
                $this->cond = array_merge($this->cond, array_fill(0, count($words), "m.Body LIKE concat('%', ?, '%')"));
                $this->args = array_merge($this->args, $words);
                break;
           case 'user':
                $this->join[] = 'INNER JOIN users_main AS um ON (um.ID = other.UserID)';
                $this->cond[] = "um.Username LIKE concat('%', ?, '%')";
                $this->args[] = $search;
                break;
            default:
                $this->failure('bad search type');
                break;
        }
    }

    public function payload(): ?array {
        $total = $this->db->scalar("
            SELECT count(DISTINCT c.ID)
            FROM pm_conversations AS c
            INNER JOIN pm_conversations_users AS cu ON (cu.ConvID = c.ID AND cu.UserID = ?)
            LEFT JOIN pm_conversations_users AS other ON (other.ConvID = c.ID AND other.UserID != ? AND other.ForwardedTo = 0)
            " . implode(' ', $this->join) . "
            WHERE " . implode(' AND ', $this->cond) ."
            ", $this->userId, $this->userId, ...$this->args
        );

        [$page, $limit] = \Format::page_limit(MESSAGES_PER_PAGE);
        $page = (int)$page;

        $unreadSort = $this->unreadFirst ? "cu.Unread = '1' DESC" : '';
        $this->db->prepared_query("
            SELECT c.ID,
                c.Subject,
                cu.Unread,
                cu.Sticky,
                cu.ForwardedTo,
                other.UserID,
                (donor.UserID IS NOT NULL) AS Donor,
                {$this->dateColumn} AS action_date
            FROM pm_conversations AS c
            INNER JOIN pm_conversations_users AS cu ON (cu.ConvID = c.ID AND cu.UserID = ?)
            LEFT JOIN pm_conversations_users AS other ON (other.ConvID = c.ID AND other.UserID != ? AND other.ForwardedTo = 0)
            LEFT JOIN users_levels AS donor ON (donor.UserID = other.UserID
                AND donor.PermissionID = (SELECT ID FROM permissions WHERE Name = 'Donor' LIMIT 1)
            )
            " . implode(' ', $this->join) . "
            WHERE " . implode(' AND ', $this->cond) ."
            GROUP BY c.ID
            ORDER BY cu.Sticky, {$unreadSort}, action_date
            LIMIT {$limit}
            ", $this->userId, $this->userId, ...$this->args
        );

        $user = [];
        $messages = [];
        while ([$convId, $subject, $unread, $sticky, $forwardedId, $senderId, $donor, $actionDate] = $this->db->next_record()) {
            $senderId = (int)$senderId;
            if (!isset($user[$senderId])) {
                $user[$senderId] = \Users::user_info($senderId);
            }
            $forwardedId = (int)$forwardedId;
            if ($forwardedId && !isset($user[$forwardedId])) {
                $user[$forwardedId] = \Users::user_info($forwardedId);
            }
            $messages[] = [
                'convId'        => (int)$convId,
                'subject'       => $subject,
                'unread'        => $unread == 1,
                'sticky'        => $sticky == 1,
                'forwardedId'   => $forwardedId,
                'forwardedName' => $forwardedId ? $user[$forwardedId]['Username'] : null,
                'senderId'      => $senderId,
                'username'      => $user[$senderId]['Username'],
                'avatar'        => $user[$senderId]['Avatar'],
                'donor'         => $donor == 1,
                'warned'        => !is_null($user[$senderId]['Warned']),
                'enabled'       => $user[$senderId]['Enabled'] == '1',
                'date'          => $actionDate,
            ];
        }
        unset($user);

        return [
            'currentPage' => $page,
            'pages'       => (int)ceil($total / MESSAGES_PER_PAGE),
            'messages'    => $messages,
        ];
    }
}
