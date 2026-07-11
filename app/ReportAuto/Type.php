<?php

declare(strict_types=1);

namespace Gazelle\ReportAuto;

/**
 * A ReportAuto\Type is a type for some report. This Type can belong to a category.
 */
class Type extends \Gazelle\Base {
    protected array $info;

    public function __construct(
        public readonly int $id
    ) {}

    public function info(): array {
        if (isset($this->info)) {
            return $this->info;
        }
        $this->info = $this->pg()->rowAssocex("
            select t.name AS name,
                id_report_auto_category,
                s.name AS category,
                description
            from report_auto_type t
            left join report_auto_category s using (id_report_auto_category)
            where t.id_report_auto_type = $1
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
