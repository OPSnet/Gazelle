<?php

namespace Gazelle\Search;

class Wiki extends \Gazelle\Base {

    protected $args;
    protected $where;
    protected $orderBy;
    protected $orderDir;
    protected $viewer;

    public function __construct(\Gazelle\User $viewer, string $type, string $terms) {
        parent::__construct();
        // Break search string down into individual words
        preg_match_all('/(\S+)/', $terms, $match);
        $this->args = $match[1];
        $cond = array_fill(0, count($this->args), "$type LIKE concat('%', ?, '%')");
        $cond[] = 'MinClassRead <= ?';
        $this->args[] = $viewer->classLevel();
        $this->where = 'WHERE ' . implode(' AND ', $cond);
    }

    public function setOrderBy(string $orderBy) {
        $this->orderBy = $orderBy;
        return $this;
    }

    public function setOrderDir(string $orderDir) {
        $this->orderDir = $orderDir;
        return $this;
    }

    public function total(): int {
        return $this->db->scalar("
            SELECT count(*) FROM wiki_articles " . $this->where, ...$this->args
        );
    }

    public function page(int $limit, int $offset): array {
        $this->db->prepared_query("
            SELECT ID,
                Title,
                Date,
                Author
            FROM wiki_articles "
            . $this->where . " ORDER BY " . $this->orderBy . ' ' . $this->orderDir
            . " LIMIT ? OFFSET ?",
            ...array_merge($this->args, [$limit, $offset])
        );
        return $this->db->to_array('ID', MYSQLI_ASSOC, false);
    }
}
