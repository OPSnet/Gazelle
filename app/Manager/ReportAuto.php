<?php

declare(strict_types=1);

namespace Gazelle\Manager;

use Gazelle\User;

class ReportAuto extends \Gazelle\BaseManager {
    protected array $reportCategories;

    public function __construct(
        protected $typeMan = new ReportAutoType(),
    ) {}

    protected function categories(): array {
        if (isset($this->reportCategories)) {
            return $this->reportCategories;
        }
        $types = $this->pg()->allex("
            select
                id_report_auto_category, name
            from report_auto_category
        ");
        $this->reportCategories = [];
        foreach ($types as $row) {
            $cls = '\\Gazelle\\ReportAuto\\' . $row['name'] . 'Report';
            if (class_exists($cls)) {
                $this->reportCategories[$row['id_report_auto_category']] = $cls;
            }
        }
        return $this->reportCategories;
    }

    public function create(
        User $user,
        \Gazelle\ReportAuto\Type $type,
        array $data,
        string|null $time = null,
    ): \Gazelle\ReportAuto {
        $args = [
            $user->id,
            $type->id,
            $this->pg()->jsonConverter()->output($data),
        ];
        if ($time) {
            // time is an iso timestring
            $qryCols = ', created';
            $qryPh = ', $4';
            $args[] = $time;
        } else {
            $qryCols = '';
            $qryPh = '';
        }
        [$id, $category] = $this->pg()->rowex("
            insert into report_auto
                   (id_user, id_report_auto_type, data $qryCols)
            values ($1,      $2,                  $3    $qryPh)
            returning
                id_report_auto,
                (
                    select id_report_auto_category
                    from report_auto_type rat
                    where rat.id_report_auto_type = report_auto.id_report_auto_type
                )
            ", ...$args
        );
        return $this->instantiateReportAuto($id, $category);
    }

    public function findById(int $id): \Gazelle\ReportAuto|null {
        [$reportId, $category] = $this->pg()->rowex("
            select ra.id_report_auto,
                rat.id_report_auto_category
            from report_auto ra
            inner join report_auto_type rat using (id_report_auto_type)
            where ra.id_report_auto = $1
            ", $id
        );
        return $reportId
            ? $this->instantiateReportAuto($reportId, $category)
            : null;
    }

    /**
     * claim all unclaimed reports for $userId by $claimer
     */
    public function claimAll(User $claimer, int $userId, int|null $typeId): int {
        $typeWhere = "";
        $args = [$claimer->id, $userId];
        if (!is_null($typeId)) {
            $typeWhere = "and id_report_auto_type = {$this->pg()->placeholder(count($args))}";
            $args[] = $typeId;
        }
        return $this->pg()->query("
            update report_auto set
                id_owner = $1
            where id_owner is null
                and id_user = $2
            $typeWhere
            ", ...$args
        );
    }

    /**
     * resolve all open reports for $userId by $resolver
     */
    public function resolveAll(User $resolver, int $userId, int|null $typeId): int {
        $typeWhere = "";
        $args = [$resolver->id, $userId];
        if (!is_null($typeId)) {
            $typeWhere = "and id_report_auto_type = {$this->pg()->placeholder(count($args))}";
            $args[] = $typeId;
        }
        return $this->pg()->query("
            update report_auto set
                resolved = now(),
                id_owner = $1
            where resolved is null
                and id_user = $2
            $typeWhere
            ", ...$args
        );
    }

    /**
     * delete a comment if it was created by $user
     *
     * return >0 on success
     */
    public function deleteComment(int $commentId, User $user): int {
        return $this->pg()->query("
            delete from report_auto_comment
            where id_report_auto_comment = $1
                and id_user = $2
            ", $commentId, $user->id
        );
    }

    /**
     * edit a comment if it was created by $user
     *
     * return >0 on success
     */
    public function editComment(int $commentId, User $user, string $message): int {
        return $this->pg()->query("
            update report_auto_comment set
                comment = $1
            where id_report_auto_comment = $2
                and id_user = $3
            ", $message, $commentId, $user->id
        );
    }

    protected function instantiateReportAuto(int $id, int|null $category): \Gazelle\ReportAuto {
        $cls = $this->categories()[$category] ?? \Gazelle\ReportAuto::class;
        return new $cls($id, $this->typeMan);  /* @phpstan-ignore-line */
    }
}
