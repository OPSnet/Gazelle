<?php

namespace Gazelle\Search;

use Gazelle\Enum\SearchReportOrder;

class Report extends \Gazelle\Base {
    protected array $args = [];
    protected array $cond = [];
    protected SearchReportOrder $order;

    public function setId(int $id): static {
        $this->cond[] = 'r.ID = ?';
        $this->args[]  = $id;
        return $this;
    }

    public function setOrder(SearchReportOrder $order): static {
        $this->order = $order;
        return $this;
    }

    public function setStatus(array $status): static {
        $this->cond[] = 'r.Status in (' . placeholders($status) . ')';
        array_push($this->args, ...$status);
        return $this;
    }

    public function setTypeFilter(array $typeList): static {
        if ($typeList) {
            $this->cond[] = 'r.Type in (' . placeholders($typeList) . ')';
            array_push($this->args, ...$typeList);
        }
        return $this;
    }

    public function restrictForumMod(): static {
        $this->cond[] = "r.Type IN ('comment', 'post', 'thread')";
        return $this;
    }

    public function order(): SearchReportOrder {
        return isset($this->order) ? $this->order : SearchReportOrder::createdDesc;
    }

    public function total(): int {
        $cond = implode(' AND ', $this->cond);
        $where = $cond ? "WHERE $cond" : '';

        return (int)self::$db->scalar("
            SELECT count(*) FROM reports r $where
            ", ...$this->args
        );
    }

    public function page(int $limit, int $offset): array {
        $cond = implode(' AND ', $this->cond);
        $where = $cond ? "WHERE $cond" : '';
        $column = $this->order()->orderBy() === 'created' ? 'r.ReportedTime' : 'r.resolvedTime';

        self::$db->prepared_query("
            SELECT r.ID
            FROM reports r
            $where
            ORDER BY $column {$this->order()->direction()}
            LIMIT ? OFFSET ?
            ", ...[...$this->args, $limit, $offset]
        );
        return self::$db->collect(0, false);
    }
}
