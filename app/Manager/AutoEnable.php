<?php

namespace Gazelle\Manager;

class AutoEnable extends \Gazelle\BaseManager {

    // Outcomes
    const PENDING   = 0;
    const APPROVED  = 1;
    const DENIED    = 2;
    const DISCARDED = 3;

    // search for the admin toolbox
    protected array $where = [];
    protected array $join = [];
    protected array $cond = [];
    protected array $args = [];

    // Cache key to store the number of enable requests
    const CACHE_TOTAL_OPEN = 'num_enable_requests';

    public function findById(int $enableId): ?\Gazelle\User\AutoEnable {
        [$id, $userId] = self::$db->row("
            SELECT ID, UserID FROM users_enable_requests WHERE ID = ?
            ", $enableId
        );
        return is_null($id) ? null : new \Gazelle\User\AutoEnable($id, new \Gazelle\User($userId));
    }

    /**
     * Note: findByUser() will return the *most recent* enable request
     */
    public function findByUser(\Gazelle\User $user): ?\Gazelle\User\AutoEnable {
        $id = self::$db->scalar("
            SELECT ID
            FROM users_enable_requests
            WHERE UserID = ?
            ORDER BY ID DESC
            LIMIT 1
            ", $user->id()
        );
        return is_null($id) ? null : new \Gazelle\User\AutoEnable($id, $user);
    }

    public function findByToken(string $token): ?\Gazelle\User\AutoEnable {
        [$id, $userId] = self::$db->row("
            SELECT ID, UserID FROM users_enable_requests WHERE Token = ?
            ", $token
        );
        return is_null($id) ? null : new \Gazelle\User\AutoEnable($id, new \Gazelle\User($userId));
    }

    public function openTotal(): int {
        $total = self::$cache->get_value(self::CACHE_TOTAL_OPEN);
        if ($total === false) {
            $total = self::$db->scalar("SELECT count(*) FROM users_enable_requests WHERE Outcome IS NULL");
            self::$cache->cache_value(self::CACHE_TOTAL_OPEN, $total);
        }
        return $total;
    }

    /**
     * Handle a new enable request
     */
    public function create(\Gazelle\User $user, string $email): ?\Gazelle\User\AutoEnable {
        $enabler = $this->findByUser($user);
        if ($enabler) {
            if ($enabler->isRejected() && $enabler->createdAfter('-2 MONTH')) {
                return null;
            }
            if ($enabler->isPending()) {
                if ($enabler->createdBefore('-1 DAY')) {
                    $user->addStaffNote("Additional enable request rejected from {$_SERVER['REMOTE_ADDR']}")->modify();
                }
                return $enabler;
            }
        }

        self::$db->prepared_query("
            INSERT INTO users_enable_requests
                   (Email, IP, UserAgent, UserID, Timestamp)
            VALUES (?,     ?,  ?,         ?,      now())
            ", $email, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], $user->id()
        );
        $enablerId = self::$db->inserted_id();
        $user->addStaffNote("Enable request $enablerId received from {$_SERVER['REMOTE_ADDR']}")->modify();
        self::$cache->delete_value(self::CACHE_TOTAL_OPEN);

        return $this->findById($enablerId);
    }

    public function adminTotal(): array {
        self::$db->prepared_query("
            SELECT CheckedBy AS user_id,
                count(*)     AS total
            FROM users_enable_requests
            WHERE CheckedBy IS NOT NULL
            GROUP BY CheckedBy
            ORDER BY 2 DESC, 1
        ");
        return self::$db->to_array(false, MYSQLI_ASSOC, false);
    }

    public function configureView(string $view, bool $showChecked): AutoEnable {
        $this->cond[] = $showChecked
            ? 'uer.Outcome IS NOT NULL'
            : 'uer.Outcome IS NULL';
        switch ($view) {
            case 'perfect':
                $this->join[] = "INNER JOIN users_main um ON (um.ID = uer.UserID)";
                $this->cond[] = "um.Email = uer.Email";
                $this->cond[] = "uer.IP = (SELECT IP FROM users_history_ips uhi1 WHERE uhi1.StartTime = (SELECT MAX(StartTime) FROM users_history_ips uhi2 WHERE uhi2.UserID = uer.UserID ORDER BY StartTime DESC LIMIT 1))";
                $this->cond[] = "(SELECT 1 FROM users_history_ips uhi WHERE uhi.IP = uer.IP AND uhi.UserID != uer.UserID) IS NULL";
                $this->cond[] = "ui.BanReason = '3'";
                break;
            case 'minus_ip':
                $this->join[] = "INNER JOIN users_main um ON (um.ID = uer.UserID)";
                $this->cond[] = "um.Email = uer.Email";
                $this->cond[] = "ui.BanReason = '3'";
                break;
            case 'invalid_email':
                $this->join[] = "INNER JOIN users_main um ON (um.ID = uer.UserID)";
                $this->cond[] = "um.Email != uer.Email";
                break;
            case 'ip_overlap':
                $this->join[] = "INNER JOIN users_history_ips uhi ON (uhi.IP = uer.IP AND uhi.UserID != uer.UserID)";
            case 'manual_disable':
                $this->cond[] = "ui.BanReason != '3'";
                break;
            default:
                break;
        }
        return $this;
    }

    public function filterUsername(string $username): AutoEnable {
        $this->join[] = "INNER JOIN users_main um1 ON (um1.ID = uer.UserID)";
        $this->cond[] = "um1.Username = ?";
        $this->args[] = $username;
        return $this;
    }

    public function filterAdmin(string $username): AutoEnable {
        $this->join[] = "INNER JOIN users_main um2 ON (um2.ID = uer.CheckedBy)";
        $this->cond[] = "um2.Username = ?";
        $this->args[] = $username;
        return $this;
    }

    public function total(): int {
        $joinList = implode(' ', $this->join);
        $where = $this->cond ? ('WHERE ' . implode(' AND ', $this->cond)) : '';
        return self::$db->scalar("
            SELECT count(*)
            FROM users_enable_requests AS uer
            INNER JOIN users_info ui ON (ui.UserID = uer.UserID)
            $joinList
            $where
            ", ...$this->args
        );
    }

    public function page(string $orderBy, string $dir, int $limit, int $offset): array {
        $joinList = implode(' ', $this->join);
        $where = $this->cond ? ('WHERE ' . implode(' AND ', $this->cond)) : '';
        self::$db->prepared_query("
            SELECT uer.ID
            FROM users_enable_requests AS uer
            INNER JOIN users_info ui ON (ui.UserID = uer.UserID)
            $joinList
            $where
            ORDER BY $orderBy $dir
            LIMIT ? OFFSET ?
            ", ...[...$this->args, $limit, $offset]
        );
        $list = [];
        foreach (self::$db->collect(0, false) as $id) {
            $list[] = $this->findById($id);
        }
        return $list;
    }

    /**
     * Handle requests
     */
    public function resolveList(\Gazelle\User $admin, array $idList, int $status, string $comment): int {
        $handled = 0;
        foreach ($idList as $id) {
            $handled += (int)$this->findById($id)?->resolve($admin, $status, $comment);
        }
        return $handled;
    }
}
