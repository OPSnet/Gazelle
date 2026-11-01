<?php

declare(strict_types=1);

namespace Gazelle\Search;

class Email extends \Gazelle\Base {
    final public const ASC = 0;
    final public const DESC = 1;

    final public const EMAIL   = 0;
    final public const USER    = 1;
    final public const JOINED  = 2;
    final public const CHANGED = 3;
    final public const IP      = 4;

    protected string $table;
    protected int    $column    = 0;
    protected int    $direction = 0;

    public function __construct(
        protected ASN $asn = new ASN(),
    ) {
        $this->table = 'email_' . randomString(8);
        $this->pg()->query("
            create temporary table {$this->table} (
                email text not null primary key
            )
        ");
    }

    public function setColumn(int $column): static {
        $this->column = $column;
        return $this;
    }

    public function setDirection(int $direction): static {
        $this->direction = $direction;
        return $this;
    }

    public function extract(string $text): array {
        if (!preg_match_all('/([\w.+-]+@[\w.-]+)/', $text, $match)) {
            return [];
        }
        return array_unique($match[0]);
    }

    public function add(array $list): int {
        if ($list === []) {
            return 0;
        }
        return $this->pg()->query(<<<END_SQL
            insert into {$this->table}
                select "Email"
                from relay.users_main
                where "Email" = any($1)
            union
                select email
                from history_email
                where email = any($1)
            on conflict do nothing
            END_SQL,
                $this->pg()->arrayConverter()->output($list),
        );
    }

    public function emailList(): array {
        return $this->pg()->columnex("
            select email from {$this->table} order by 1
        ");
    }

    public function liveTotal(): int {
        return $this->pg()->scalarex(<<<END_SQL
            select count(*)
            from relay.users_main     um
            inner join {$this->table} s on (s.email = um."Email")
        END_SQL);
    }

    public function liveList(int $limit, int $offset): array {
        $column = ['um."Email"', 'um."Username"', 'um.created', 'um.created', 'um."IP"::inet'][$this->column];
        $direction = ['ASC', 'DESC'][$this->direction];

        $result = $this->pg()->allex(<<<END_SQL
            select um."Email" as email,
                um."Username" as username,
                um."ID"       as user_id,
                um.created    as created,
                um."IP"::inet as ipv4
            from relay.users_main     um
            inner join {$this->table} s on (s.email = um."Email")
            order by $column $direction
            limit $1 offset $2
            END_SQL,
                $limit, $offset
        );
        $asnList = $this->asn->findByIpList(array_column($result, 'ipv4'));
        foreach ($result as &$row) {
            $row['cc']     = $asnList[$row['ipv4']]['cc'];
            $row['is_tor'] = $asnList[$row['ipv4']]['is_tor'];
            $row['n']      = $asnList[$row['ipv4']]['n'];
            $row['name']   = $asnList[$row['ipv4']]['name'];
        }
        return $result;
    }

    public function historyTotal(): int {
        return $this->pg()->scalarex("
            select count(*)
            from history_email he
            inner join {$this->table} s using (email)
        ");
    }

    public function historyList(int $limit, int $offset): array {
        $column = ['he.email', 'um."Username"', 'um.created', 'he.created', 'he.ip'][$this->column];
        $direction = ['ASC', 'DESC'][$this->direction];
        $result = $this->pg()->allex(<<<END_SQL
            select he.email   as email,
                um."Username" as username,
                um."ID"       as user_id,
                um.created    as created,
                he.created    as change_date,
                he.ip         as ipv4
            from history_email          he
            inner join {$this->table}   s using (email)
            inner join relay.users_main um on (um."ID" = he.id_user)
            where (
                (um.created = he.created and he.email != um."Email")
                or um.created != he.created
            )
            order by $column $direction
            limit $1 offset $2
            END_SQL,
                $limit, $offset
        );
        $asnList = $this->asn->findByIpList(array_column($result, 'ipv4'));
        foreach ($result as &$row) {
            $row['cc']     = $asnList[$row['ipv4']]['cc'];
            $row['is_tor'] = $asnList[$row['ipv4']]['is_tor'];
            $row['n']      = $asnList[$row['ipv4']]['n'];
            $row['name']   = $asnList[$row['ipv4']]['name'];
        }
        return $result;
    }
}
