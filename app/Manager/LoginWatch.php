<?php

namespace Gazelle\Manager;

use Gazelle\Util\SortableTableHeader;

class LoginWatch extends \Gazelle\Base {
    public function header(): SortableTableHeader {
        return new SortableTableHeader(
            'modified',
            [
                'user'          => ['dbColumn' => 'w.capture',       'defaultSort' => 'asc', 'text' => 'User'],
                'ipaddr'        => ['dbColumn' => 'w.ip',            'defaultSort' => 'asc',  'text' => 'IP'],
                'attempt'       => ['dbColumn' => 'w.attempt',       'defaultSort' => 'desc', 'text' => 'Attempts'],
                'blocked'       => ['dbColumn' => 'w.block',         'defaultSort' => 'desc', 'text' => 'Blocks'],
                'modified'      => ['dbColumn' => 'w.modified',      'defaultSort' => 'desc', 'text' => 'Last Attempt'],
                'blocked_until' => ['dbColumn' => 'w.blocked_until', 'defaultSort' => 'desc', 'text' => 'Login Forbidden'],
            ]
        );
    }

    public function activeTotal(): int {
        return (int)$this->pg()->scalarex(<<<END_SQL
            select count(*)
            from login_watch
            where (blocked_until > now() - interval '24 hour' and block > 0)
                or (modified > now() - interval '6 hour' and attempt > 0)
        END_SQL
        );
    }

    public function activeList(int $limit, int $offset): array {
        return $this->pg()->allex(<<<END_SQL
            SELECT w.ip,
                w.id_user,
                w.attempt,
                w.block,
                w.blocked_until,
                w.capture,
                w.modified    as last_attempt,
                (ib.ip is not null) as banned
            from login_watch w
            left join ip_ban ib using (ip)
            where (w.blocked_until > now() - interval '24 hour' and block > 0)
                or (w.modified > now() - interval '6 hour' and attempt > 0)
            order by {$this->header()->orderBy()} {$this->header()->dir()}
            limit $1 offset $2
            END_SQL,
                $limit,
                $offset,
        );
    }
}
