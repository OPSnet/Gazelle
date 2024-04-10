<?php

namespace Gazelle\Manager;

class ReportAuto extends \Gazelle\BaseManager {
    use \Gazelle\Pg;
    protected array $reportCategories;

    public function __construct(
        protected $typeMan = new \Gazelle\Manager\ReportAutoType(),
    ) {}

    protected function categories(): array {
        if (isset($this->reportCategories)) {
            return $this->reportCategories;
        }
        $types = $this->pg()->all("
            SELECT
                id_report_auto_category, name
            FROM report_auto_category
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

    public function create(\Gazelle\User $user, \Gazelle\ReportAuto\Type $type, array $data, string $time = null): \Gazelle\ReportAuto {
        $args = [$user->id(), $type->id(), json_encode($data)];
        if ($time) {
            // time is an iso timestring
            $qryCols = ', created';
            $qryPh = ', ?';
            $args[] = $time;
        } else {
            $qryCols = '';
            $qryPh = '';
        }
        [$id, $category] = $this->pg()->row("
            INSERT INTO report_auto
                   (id_user, id_report_auto_type, data $qryCols)
            VALUES (?,       ?,                   ?    $qryPh)
            RETURNING id_report_auto, (SELECT id_report_auto_category
                                       FROM report_auto_type rat
                                       WHERE rat.id_report_auto_type = report_auto.id_report_auto_type)
            ", ...$args
        );
        return $this->instantiateReportAuto((int)$id, $category);
    }

    public function findById(int $reportId): ?\Gazelle\ReportAuto {
        [$id, $category] = $this->pg()->row("
            SELECT ra.id_report_auto, rat.id_report_auto_category
            FROM report_auto ra JOIN report_auto_type rat USING (id_report_auto_type)
            WHERE ra.id_report_auto = ?
            ", $reportId
        );
        return is_null($id) ? null : $this->instantiateReportAuto((int)$id, $category);
    }

    /**
     * claim all unclaimed reports for $userId by $claimer
     */
    public function claimAll(\Gazelle\User $claimer, int $userId, ?int $typeId): int {
        $typeWhere = "";
        $args = [];
        if (!is_null($typeId)) {
            $typeWhere = "AND id_report_auto_type = ?";
            $args[] = $typeId;
        }
        return $this->pg()->prepared_query("
            UPDATE report_auto SET
              id_owner = ?
            WHERE id_user = ? AND id_owner IS NULL
            $typeWhere
        ", $claimer->id(), $userId, ...$args);
    }

    /**
     * resolve all open reports for $userId by $resolver
     */
    public function resolveAll(\Gazelle\User $resolver, int $userId, ?int $typeId): int {
        $typeWhere = "";
        $args = [];
        if (!is_null($typeId)) {
            $typeWhere = "AND id_report_auto_type = ?";
            $args[] = $typeId;
        }
        return $this->pg()->prepared_query("
            UPDATE report_auto
            SET id_owner = ?, resolved = now()
            WHERE id_user = ? AND resolved IS NULL
            $typeWhere
        ", $resolver->id(), $userId, ...$args);
    }

    /**
     * delete a comment if it was created by $user
     *
     * return >0 on success
     */
    public function deleteComment(int $commentId, \Gazelle\User $user): int {
        return $this->pg()->prepared_query("
            DELETE FROM report_auto_comment
            WHERE id_report_auto_comment = ? AND id_user = ?
        ", $commentId, $user->id());
    }

    /**
     * edit a comment if it was created by $user
     *
     * return >0 on success
     */
    public function editComment(int $commentId, \Gazelle\User $user, string $message): int {
        return $this->pg()->prepared_query("
            UPDATE report_auto_comment SET
              comment = ?
            WHERE id_report_auto_comment = ? AND id_user = ?
        ", $message, $commentId, $user->id());
    }

    protected function instantiateReportAuto(int $id, ?int $category): \Gazelle\ReportAuto {
        $cls = $this->categories()[$category] ?? '\\Gazelle\\ReportAuto';
        return new $cls($id, $this->typeMan);  /* @phpstan-ignore-line */
    }
}
