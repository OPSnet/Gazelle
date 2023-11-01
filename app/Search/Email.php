<?php

namespace Gazelle\Search;

class Email extends \Gazelle\Base {
    final public const ASC = 0;
    final public const DESC = 1;

    final public const EMAIL   = 0;
    final public const USER    = 1;
    final public const JOINED  = 2;
    final public const CHANGED = 3;
    final public const IP      = 4;

    protected string $name;
    protected int $column = 0;
    protected int $direction = 0;

    public function __construct(
        protected ASN $asn,
    ) {}

    public function __destruct() {
        if (isset($this->name)) {
            self::$db->dropTemporaryTable($this->name);
        }
    }

    public function setColumn(int $column): static {
        $this->column = $column;
        return $this;
    }

    public function setDirection(int $direction): static {
        $this->direction = $direction;
        return $this;
    }

    public function create(string $name): static {
        $this->name = $name;
        self::$db->dropTemporaryTable($this->name);
        self::$db->prepared_query("
            CREATE TEMPORARY TABLE {$this->name} (
                email varchar(255) NOT NULL PRIMARY KEY
            )
        ");
        return $this;
    }

    public function extract(string $text): array {
        if (!preg_match_all('/([\w.+-]+@[\w.-]+)/', $text, $match)) {
            return [];
        }
        return array_unique($match[0]);
    }

    public function add(array $list): int {
        if (!$list) {
            return 0;
        }
        $placeholders = placeholders($list);
        self::$db->prepared_query("
            INSERT IGNORE INTO {$this->name} (email)
            SELECT Email FROM users_main
            WHERE Email IN ($placeholders)
            UNION
            SELECT Email FROM users_history_emails
            WHERE Email IN ($placeholders)
            ", ...$list, ...$list
        );
        return self::$db->affected_rows();
    }

    public function emailList(): array {
        self::$db->prepared_query("
            SELECT email FROM {$this->name} ORDER BY email
        ");
        return self::$db->collect(0);
    }

    public function liveTotal(): int {
        return (int)self::$db->scalar("
            SELECT count(*)
            FROM users_main um
            INNER JOIN {$this->name} s ON (s.email = um.Email)
        ");
    }

    public function liveList(int $limit, int $offset): array {
        $column = ['um.Email', 'um.Username', 'um.created', 'um.created', 'inet_aton(um.IP)'][$this->column];
        $direction = ['ASC', 'DESC'][$this->direction];

        self::$db->prepared_query("
            SELECT um.Email AS email,
                um.Username AS username,
                um.ID       AS user_id,
                um.created  AS created,
                um.IP       AS ipv4
            FROM users_main         um
            INNER JOIN {$this->name} s ON (s.email = um.Email)
            ORDER BY $column $direction
            LIMIT ? OFFSET ?
            ", $limit, $offset
        );
        $asnList = $this->asn->findByIpList(self::$db->collect('ipv4', false));
        $list = self::$db->to_array(false, MYSQLI_ASSOC, false);
        foreach ($list as &$row) {
            $row['cc']     = $asnList[$row['ipv4']]['cc'];
            $row['is_tor'] = $asnList[$row['ipv4']]['is_tor'];
            $row['n']      = $asnList[$row['ipv4']]['n'];
            $row['name']   = $asnList[$row['ipv4']]['name'];
        }
        return $list;
    }

    public function historyTotal(): int {
        return (int)self::$db->scalar("
            SELECT count(*)
            FROM users_history_emails uhe
            INNER JOIN {$this->name} s ON (s.email = uhe.Email)
        ");
    }

    public function historyList(int $limit, int $offset): array {
        $column = ['uhe.Email', 'um.Username', 'um.created', 'uhe.created', 'inet_aton(uhe.IP)'][$this->column];
        $direction = ['ASC', 'DESC'][$this->direction];
        self::$db->prepared_query("
            SELECT uhe.Email AS email,
                um.Username  AS username,
                um.ID        AS user_id,
                um.created   AS created,
                uhe.created  AS change_date,
                uhe.IP       AS ipv4
            FROM users_history_emails uhe
            INNER JOIN {$this->name} s ON (s.email = uhe.Email)
            INNER JOIN users_main   um ON (um.ID = uhe.UserID)
            WHERE ((um.created = uhe.Time and uhe.Email != um.Email) OR um.created != uhe.Time)
            ORDER BY $column $direction
            LIMIT ? OFFSET ?
            ", $limit, $offset
        );
        $asnList = $this->asn->findByIpList(self::$db->collect('ipv4', false));
        $list = self::$db->to_array(false, MYSQLI_ASSOC, false);
        foreach ($list as &$row) {
            $row['cc']     = $asnList[$row['ipv4']]['cc'];
            $row['is_tor'] = $asnList[$row['ipv4']]['is_tor'];
            $row['n']      = $asnList[$row['ipv4']]['n'];
            $row['name']   = $asnList[$row['ipv4']]['name'];
        }
        return $list;
    }
}
