<?php

declare(strict_types=1);

namespace Gazelle\Search;

use Gazelle\Enum\ReportAutoState;
use Gazelle\Manager\ReportAuto     as ReportAutoManager;
use Gazelle\Manager\ReportAutoType as ReportAutoTypeManager;
use Gazelle\Manager\User           as UserManager;
use Gazelle\ReportAuto\Type        as ReportAutoType;
use Gazelle\User;

class ReportAuto extends \Gazelle\Base {
    protected array $args = [];
    protected array $cond = [];

    public function __construct(
        protected ReportAutoManager $reportMan,
        protected ReportAutoTypeManager $typeMan,
    ) {}

    public function setId(int $id): ReportAuto {
        $this->cond[] = "id_report_auto = {$this->pg()->placeholder(count($this->args))}";
        $this->args[] = $id;
        return $this;
    }

    public function setOwner(User|null $user): ReportAuto {
        if (!$user) {
            $this->cond[] = 'id_owner is null';
        } else {
            $this->cond[] = "id_owner = {$this->pg()->placeholder(count($this->args))}";
            $this->args[] = $user->id;
        }
        return $this;
    }

    public function setState(ReportAutoState $state): ReportAuto {
        $this->cond[] = match ($state) {
            ReportAutoState::open        => 'resolved is null',
            ReportAutoState::closed      => 'resolved is not null',
            ReportAutoState::in_progress => 'resolved is null and id_owner is not null',
        };
        return $this;
    }

    public function setType(ReportAutoType $type): ReportAuto {
        $this->cond[] = "id_report_auto_type = {$this->pg()->placeholder(count($this->args))}";
        $this->args[] = $type->id;
        return $this;
    }

    public function setUser(User $user): ReportAuto {
        $this->cond[] = "id_user = {$this->pg()->placeholder(count($this->args))}";
        $this->args[] = $user->id;
        return $this;
    }

    /**
     * number of users with matching reports
     */
    public function total(): int {
        $cond  = implode(' and ', $this->cond);
        $where = $cond ? "where $cond" : '';
        return (int)$this->pg()->scalarex("
            select count(distinct id_user) from report_auto $where
            ", ...$this->args
        );
    }

    protected function totalList(string $key, int $limit = 0): array {
        $cond  = implode(' and ', $this->cond);
        $where = $cond ? "where $cond" : '';
        $args  = $this->args; // take a copy to avoid piling up limit/offset arguments
        if ($limit) {
            $limitPh = "limit {$this->pg()->placeholder(count($args))}";
            $args[] = $limit;
        } else {
            $limitPh = '';
        }
        return $this->pg()->allex("
            select
                $key as key,
                count(*) as total
            from report_auto
            $where
            group by $key
            order by count(*) desc
            $limitPh
            ", ...$args
        );
    }

    /**
     * returns array of [\Gazelle\ReportAuto\Type, reports count] ordered by count
     */
    public function typeTotalList(): array {
        return array_map(
            fn ($row) => [$this->typeMan->findById($row['key']), $row['total']],
            $this->totalList('id_report_auto_type')
        );
    }

    /**
     * returns array of [\Gazelle\User, reports count] ordered by count
     */
    public function userTotalList(
        int $limit = 0,
        UserManager $userMan = new UserManager(),
    ): array {
        return array_map(
            fn ($row) => [$userMan->findById($row['key']), $row['total']],
            $this->totalList('id_user', $limit)
        );
    }

    /**
     * returns array of userId => [ReportAuto obj,...]
     */
    public function page(int $limit, int $offset): array {
        $cond     = implode(' and ', $this->cond);
        $where    = $cond ? "where $cond" : '';
        $args     = $this->args;
        $limitPh  = "limit {$this->pg()->placeholder(count($args))}";
        $args[]   = $limit;
        $offsetPh = "offset {$this->pg()->placeholder(count($args))}";
        $args[]   = $offset;
        $list     = $this->pg()->allByKeyex(
            'id_user', <<<END_SQL
            select id_user,
                array_agg(id_report_auto order by id_report_auto desc) as id_reports
            from report_auto
            $where
            group by id_user
            order by max(created) desc, id_user
            $limitPh $offsetPh
            END_SQL,
                ...$args
        );
        $reports = [];
        foreach ($list as $userId => $row) {
            $reports[$userId] = array_map(
                fn ($r) => $this->reportMan->findById((int)$r),
                $row['id_reports'],
            );
        }
        return $reports;
    }
}
