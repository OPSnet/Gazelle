<?php

namespace Gazelle\Search;

class Email extends \Gazelle\Base {
    public const ASC = 0;
    public const DESC = 1;

    public const EMAIL   = 0;
    public const USER    = 1;
    public const JOINED  = 2;
    public const CHANGED = 3;
    public const IP      = 4;

    protected string $name;
    protected int $column = 0;
    protected int $direction = 0;

    public function __construct(
        protected ASN $asn,
    ) {}

    public function setColumn(int $column) {
        $this->column = $column;
        return $this;
    }

    public function setDirection(int $direction) {
        $this->direction = $direction;
        return $this;
    }

    public function create(string $name) {
        $this->name = $name;
        self::$db->prepared_query("
            CREATE TEMPORARY TABLE {$this->name} (
                email varchar(255) NOT NULL PRIMARY KEY
            ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
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
        return self::$db->scalar("
            SELECT count(*)
            FROM users_main um
            INNER JOIN {$this->name} s ON (s.email = um.Email)
        ");
    }

    public function liveList(int $limit, int $offset): array {
        $column = ['um.Email', 'um.Username', 'ui.JoinDate', 'ui.JoinDate', 'inet_aton(um.IP)'][$this->column];
        $direction = ['ASC', 'DESC'][$this->direction];

        self::$db->prepared_query("
            SELECT um.Email AS email,
                um.Username AS username,
                um.ID       AS user_id,
                ui.JoinDate AS join_date,
                um.IP       AS ipv4
            FROM users_main         um
            INNER JOIN users_info   ui ON (ui.UserID = um.ID)
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
        return self::$db->scalar("
            SELECT count(*)
            FROM users_history_emails uhe
            INNER JOIN {$this->name} s ON (s.email = uhe.Email)
        ");
    }

    public function historyList(int $limit, int $offset): array {
        $column = ['uhe.Email', 'um.Username', 'ui.JoinDate', 'uhe.Time', 'inet_aton(uhe.IP)'][$this->column];
        $direction = ['ASC', 'DESC'][$this->direction];
        self::$db->prepared_query("
            SELECT uhe.Email AS email,
                um.Username  AS username,
                um.ID        AS user_id,
                ui.JoinDate  AS join_date,
                uhe.Time     AS change_date,
                uhe.IP       AS ipv4
            FROM users_history_emails uhe
            INNER JOIN {$this->name} s ON (s.email = uhe.Email)
            INNER JOIN users_main   um ON (um.ID = uhe.UserID)
            INNER JOIN users_info   ui ON (ui.UserID = um.ID)
            WHERE ui.JoinDate != uhe.Time
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
