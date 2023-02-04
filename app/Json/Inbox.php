<?php

namespace Gazelle\Json;

class Inbox extends \Gazelle\Json {
    protected string $dateColumn;

    protected array $cond = [];
    protected array $args = [];
    protected array $join = [];

    public function __construct(
        protected \Gazelle\User $user,
        protected string $folder,
        protected int $page,
        protected bool $unreadFirst,
        protected \Gazelle\Manager\User $userMan,
    ) {}

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
        switch ($this->folder) {
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
        $total = self::$db->scalar("
            SELECT count(DISTINCT c.ID)
            FROM pm_conversations AS c
            INNER JOIN pm_conversations_users AS cu ON (cu.ConvID = c.ID AND cu.UserID = ?)
            LEFT JOIN pm_conversations_users AS other ON (other.ConvID = c.ID AND other.UserID != ? AND other.ForwardedTo = 0)
            " . implode(' ', $this->join) . "
            WHERE " . implode(' AND ', $this->cond) ."
            ", $this->user->id(), $this->user->id(), ...$this->args
        );
        $paginator = new \Gazelle\Util\Paginator(MESSAGES_PER_PAGE, $this->page);
        $paginator->setTotal($total);
        array_push($this->args, $paginator->limit(), $paginator->offset());

        $orderBy = $this->unreadFirst ? "cu.Unread = '1' DESC, action_date ASC" : 'action_date ASC';
        self::$db->prepared_query("
            SELECT c.ID,
                c.Subject,
                cu.Unread,
                cu.Sticky,
                cu.ForwardedTo,
                other.UserID,
                {$this->dateColumn} AS action_date
            FROM pm_conversations AS c
            INNER JOIN pm_conversations_users AS cu ON (cu.ConvID = c.ID AND cu.UserID = ?)
            LEFT JOIN pm_conversations_users AS other ON (other.ConvID = c.ID AND other.UserID != ? AND other.ForwardedTo = 0)
            " . implode(' ', $this->join) . "
            WHERE " . implode(' AND ', $this->cond) ."
            GROUP BY c.ID
            ORDER BY cu.Sticky, {$orderBy}
            LIMIT ? OFFSET ?
            ", $this->user->id(), $this->user->id(), ...$this->args
        );

        $user = [];
        $messages = [];
        $qid = self::$db->get_query_id();
        while ([$convId, $subject, $unread, $sticky, $forwardedId, $senderId, $actionDate] = self::$db->next_record()) {
            $senderId = (int)$senderId;
            if ($senderId && !isset($user[$senderId])) {
                $user[$senderId] = $this->userMan->findById($senderId);
            }
            $forwardedId = (int)$forwardedId;
            if ($forwardedId && !isset($user[$forwardedId])) {
                $user[$forwardedId] = $this->userMan->findById($forwardedId);
            }
            $messages[] = [
                'convId'        => (int)$convId,
                'subject'       => $subject,
                'unread'        => $unread == 1,
                'sticky'        => $sticky == 1,
                'forwardedId'   => $forwardedId,
                'forwardedName' => $forwardedId ? $user[$forwardedId]->username() : null,
                'senderId'      => $senderId,
                'username'      => $senderId ? $user[$senderId]->username() : 'System',
                'avatar'        => $senderId ? $user[$senderId]->avatar() : null,
                'warned'        => $senderId && $user[$senderId]->isWarned(),
                'enabled'       => $senderId && $user[$senderId]->isEnabled(),
                'donor'         => $senderId && (new \Gazelle\User\Privilege($user[$senderId]))->isDonor(),
                'date'          => $actionDate,
            ];
            self::$db->set_query_id($qid);
        }
        unset($user);

        return [
            'currentPage' => $paginator->page(),
            'pages'       => (int)ceil($total / $paginator->limit()),
            'messages'    => $messages,
        ];
    }
}
