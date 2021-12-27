<?php

namespace Gazelle\Search;

class Donation extends \Gazelle\Base {

    protected array $join = [];
    protected array $cond = [];
    protected array $args = [];

    protected string $_join;
    protected string $_where;

    public function setUsername(string $username) {
        $this->join[] = "INNER JOIN users_main AS m ON (m.ID = d.UserID)";
        $this->cond[] = "m.Username LIKE concat('%', ?, '%')";
        $this->args[] = trim($username);
        return $this;
    }

    public function setInterval(string $after, string $before) {
        $this->cond[] = "d.Time BETWEEN ? AND ?";
        array_push($this->args, trim($after), trim($before));
        return $this;
    }

    protected function configure() {
        if (!isset($this->_where)) {
            $this->_where = empty($this->cond) ? '' : ('WHERE ' . implode(' AND ', $this->cond));
            $this->_join = implode(' ', $this->join);
        }
    }

    public function total(): int {
        $this->configure();
        return self::$db->scalar("
            SELECT count(*)
            FROM donations AS d
            {$this->_join} {$this->_where}
            ", ...$this->args
        );
    }

    public function page(int $limit, int $offset): array {
        $this->configure();
        self::$db->prepared_query("
            SELECT d.UserID,
                d.Amount,
                d.Currency,
                d.xbt,
                d.Time,
                d.Source,
                d.AddedBy,
                d.Reason
            FROM donations AS d
            {$this->_join} {$this->_where}
            ORDER BY d.Time DESC
            LIMIT ? OFFSET ?
            ", ...[...$this->args, $limit, $offset]
        );
        return self::$db->to_array(false, MYSQLI_ASSOC, false);
    }
}
