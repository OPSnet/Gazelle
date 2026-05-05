<?php

namespace Gazelle\Manager;

class AutoEnable extends \Gazelle\BaseManager {
    // Outcomes
    final public const PENDING   = 0;
    final public const APPROVED  = 1;
    final public const DENIED    = 2;
    final public const DISCARDED = 3;

    // search for the admin toolbox
    protected array $where = [];
    protected array $join = [];
    protected array $cond = [];
    protected array $args = [];

    // Cache key to store the number of enable requests
    final public const CACHE_TOTAL_OPEN = 'num_enable_requests';

    /**
     * Handle a new enable request
     */
    public function create(\Gazelle\User $user, string $email): ?\Gazelle\User\AutoEnable {
        $enabler = $this->findByUser($user);
        $remoteAddr = $user->requestContext()->remoteAddr();
        if ($enabler) {
            if ($enabler->isRejected() && $enabler->createdAfter('-2 MONTH')) {
                return null;
            }
            if ($enabler->isPending()) {
                if ($enabler->createdBefore('-1 DAY')) {
                    $user->addStaffNote("Additional enable request rejected from $remoteAddr")->modify();
                }
                return $enabler;
            }
        }

        self::$db->prepared_query("
            INSERT INTO users_enable_requests
                   (Email, IP, UserAgent, UserID, Timestamp)
            VALUES (?,     ?,  ?,         ?,      now())
            ", $email, $remoteAddr, $user->requestContext()->useragent(), $user->id
        );
        $enablerId = self::$db->inserted_id();
        $user->addStaffNote("Enable request $enablerId received from {$remoteAddr}")->modify();
        self::$cache->delete_value(self::CACHE_TOTAL_OPEN);

        return $this->findById($enablerId);
    }

    public function findById(int $id): ?\Gazelle\User\AutoEnable {
        [$enableId, $userId] = self::$db->row("
            SELECT ID, UserID FROM users_enable_requests WHERE ID = ?
            ", $id
        );
        return $enableId
            ? new \Gazelle\User\AutoEnable($enableId, new \Gazelle\User($userId))
            : null;
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
            ", $user->id
        );
        return is_null($id) ? null : new \Gazelle\User\AutoEnable((int)$id, $user);
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
            $total = (int)self::$db->scalar("
                SELECT count(*) FROM users_enable_requests WHERE Outcome IS NULL
            ");
            self::$cache->cache_value(self::CACHE_TOTAL_OPEN, $total);
        }
        return $total;
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
        return self::$db->to_array(false, MYSQLI_ASSOC);
    }

    public function configureView(string $view, bool $showChecked): static {
        $this->cond[] = $showChecked
            ? 'uer."Outcome" IS NOT NULL'
            : 'uer."Outcome" IS NULL';
        switch ($view) {
            case 'perfect':
                $this->join[] = 'INNER JOIN relay.users_main um ON (um."ID" = uer."UserID")';
                $this->cond[] = 'um."Email" = uer."Email"';
                $this->cond[] = 'uer."IP" = (
                    select ip from ip_site_history
                    where id_user = uer."UserID" 
                    order by upper(seen) desc limit 1
                )';
                $this->cond[] = '(SELECT 1 FROM ip_site_history ih WHERE ih.ip = uer."IP" AND ih.id_user != uer."UserID") IS NULL';
                $this->cond[] = 'ui."BanReason" = "3"';
                break;
            case 'minus_ip':
                $this->join[] = 'INNER JOIN relay.users_main um ON (um."ID" = uer."UserID")';
                $this->cond[] = 'um."Email" = uer."Email"';
                $this->cond[] = 'ui."BanReason" = "3"';
                break;
            case 'invalid_email':
                $this->join[] = 'INNER JOIN relay.users_main um ON (um."ID" = uer."UserID")';
                $this->cond[] = 'um."Email" != uer."Email"';
                break;
            case 'ip_overlap':
                $this->join[] = 'INNER JOIN ip_site_history ih ON (ih.ip = uer."IP" AND ih.id_user != uer."UserID")';
                break;
            case 'manual_disable':
                $this->cond[] = 'ui."BanReason" != "3"';
                break;
            default:
                break;
        }
        return $this;
    }

    public function filterUsername(string $username): static {
        $this->join[] = 'INNER JOIN relay.users_main um1 ON (um1."ID" = uer."UserID")';
        $this->cond[] = 'um1."Username" = ' . $this->pg()->placeholder(count($this->args));
        $this->args[] = $username;
        return $this;
    }

    public function filterAdmin(string $username): static {
        $this->join[] = 'INNER JOIN relay.users_main um2 ON (um2."ID" = uer."CheckedBy")';
        $this->cond[] = 'um2."Username" = ' . $this->pg()->placeholder(count($this->args));
        $this->args[] = $username;
        return $this;
    }

    public function total(): int {
        $joinList = implode(' ', $this->join);
        $where = $this->cond ? ('WHERE ' . implode(' AND ', $this->cond)) : '';
        return $this->pg()->scalarex(<<<END_SQL
            SELECT count(*)
            FROM relay.users_enable_requests AS uer
            INNER JOIN relay.users_info ui ON (ui."UserID" = uer."UserID")
            $joinList
            $where
            END_SQL, ...$this->args
        );
    }

    public function page(string $orderBy, string $dir, int $limit, int $offset): array {
        $joinList = implode(' ', $this->join);
        $where = $this->cond ? ('WHERE ' . implode(' AND ', $this->cond)) : '';
        $userIds = $this->pg()->columnex(<<<END_SQL
            SELECT uer."ID"
            FROM relay.users_enable_requests AS uer
            INNER JOIN relay.users_info ui ON (ui."UserID" = uer."UserID")
            $joinList
            $where
            ORDER BY $orderBy $dir
            END_SQL
            . ' LIMIT ' . $this->pg()->placeholder(count($this->args))
            . ' OFFSET ' . $this->pg()->placeholder(count($this->args) + 1),
            ...[...$this->args, $limit, $offset]
        );
        return [...object_generator($this, $userIds)];
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
