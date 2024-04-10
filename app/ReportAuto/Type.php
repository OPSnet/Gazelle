<?php

namespace Gazelle\ReportAuto;

/**
 * A ReportAuto\Type is a type for some report. This Type can belong to a category.
 */
class Type extends \Gazelle\Base {
    use \Gazelle\Pg;

    protected array $info;

    public function __construct(
        protected int $id
    ) {}

    public function info(): array {
        if (isset($this->info)) {
            return $this->info;
        }
        $this->info = $this->pg()->rowAssoc("
            SELECT
                t.name AS name, id_report_auto_category, s.name AS category, description
            FROM report_auto_type t LEFT JOIN report_auto_category s USING (id_report_auto_category)
            WHERE t.id_report_auto_type = ?
            ", $this->id
        );
        return $this->info;
    }

    public function id(): int {
        return $this->id;
    }

    public function name(): string {
        return $this->info()['name'];
    }

    public function category(): ?string {
        return $this->info()['category'];
    }

    public function categoryId(): ?int {
        return $this->info()['id_report_auto_category'];
    }

    public function description(): string {
        return $this->info()['description'];
    }
}
