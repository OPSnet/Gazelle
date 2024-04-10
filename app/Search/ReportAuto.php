<?php

namespace Gazelle\Search;

class ReportAuto {
    use \Gazelle\Pg;

    protected array $args = [];
    protected array $cond = [];

    public function __construct(
        protected \Gazelle\Manager\ReportAuto $reportMan,
        protected \Gazelle\Manager\ReportAutoType $typeMan
    ) {}

    public function setId(int $id): ReportAuto {
        $this->cond[] = 'id_report_auto = ?';
        $this->args[] = $id;
        return $this;
    }

    public function setOwner(?\Gazelle\User $user): ReportAuto {
        if (!$user) {
            $this->cond[] = 'id_owner is NULL';
        } else {
            $this->cond[] = 'id_owner = ?';
            $this->args[] = $user->id();
        }
        return $this;
    }

    public function setState(\Gazelle\Enum\ReportAutoState $state): ReportAuto {
        $this->cond[] = match ($state) {
            \Gazelle\Enum\ReportAutoState::open        => 'resolved is NULL',
            \Gazelle\Enum\ReportAutoState::closed      => 'resolved is not NULL',
            \Gazelle\Enum\ReportAutoState::in_progress => 'resolved is NULL and id_owner is not NULL', /* @phpstan-ignore-line */
        };
        return $this;
    }

    public function setType(\Gazelle\ReportAuto\Type $type): ReportAuto {
        $this->cond[] = 'id_report_auto_type = ?';
        $this->args[] = $type->id();
        return $this;
    }

    public function setUser(\Gazelle\User $user): ReportAuto {
        $this->cond[] = 'id_user = ?';
        $this->args[] = $user->id();
        return $this;
    }

    /**
     * number of users with matching reports
     */
    public function total(): int {
        $cond = implode(' AND ', $this->cond);
        $where = $cond ? "WHERE $cond" : '';

        return (int)$this->pg()->scalar("
            SELECT count(distinct id_user) FROM report_auto $where
            ", ...$this->args
        );
    }

    protected function totalList($key, int $limit = 0): array {
        $cond = implode(' AND ', $this->cond);
        $where = $cond ? "WHERE $cond" : '';
        $limit = $limit ? "LIMIT $limit" : '';
        return $this->pg()->all("
            SELECT
                $key AS key,
                count(*) AS total
            FROM report_auto
            $where
            GROUP BY $key
            ORDER BY count(*) DESC
            $limit
        ", ...$this->args);
    }

    /**
     * returns array of [\Gazelle\ReportAuto\Type, reports count] ordered by count
     */
    public function typeTotalList(): array {
        return array_map(
            fn($row) => [$this->typeMan->findById($row['key']), $row['total']],
            $this->totalList('id_report_auto_type')
        );
    }

    /**
     * returns array of [\Gazelle\User, reports count] ordered by count
     */
    public function userTotalList(\Gazelle\Manager\User $userMan, int $limit = 0): array {
        return array_map(
            fn($row) => [$userMan->findById($row['key']), $row['total']],
            $this->totalList('id_user', $limit)
        );
    }

    /**
     * returns array of userId => [ReportAuto obj,...]
     */
    public function page(int $limit, int $offset): array {
        $cond = implode(' AND ', $this->cond);
        $where = $cond ? "WHERE $cond" : '';

        $userReportsList = $this->pg()->all("
            SELECT id_user,  array_to_string(array_agg(id_report_auto ORDER BY id_report_auto DESC), ',') as id_reports
            FROM report_auto
            $where
            GROUP BY id_user
            ORDER BY max(created) DESC, id_user
            LIMIT ? OFFSET ?
            ", ...[...$this->args, $limit, $offset]
        );

        $reports = [];
        foreach ($userReportsList as $row) {
            $userId = $row['id_user'];
            $reportIds = $row['id_reports'];
            $reports[$userId] = array_map(
                fn($r) => $this->reportMan->findById((int)$r), explode(',', $reportIds)
            );
        }
        return $reports;
    }
}
