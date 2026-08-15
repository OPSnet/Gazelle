<?php

declare(strict_types=1);

namespace Gazelle\Manager;

use Gazelle\ReportAuto\Type as ReportAutoTypeInstance;

class ReportAutoType extends \Gazelle\BaseManager {
    protected array $reportTypes = [];

    public function findById(int $id): ReportAutoTypeInstance|null {
        if (isset($this->reportTypes[$id])) {
            return $this->reportTypes[$id];
        }
        $type = new ReportAutoTypeInstance($id);
        if ($type->info() === []) {
            return null;
        }
        $this->reportTypes[$id] = $type;
        return $type;
    }

    public function findByName(string $name): ReportAutoTypeInstance|null {
        $id = $this->pg()->scalarex("
            select id_report_auto_type from report_auto_type where name = $1
            ", $name
        );
        return $id ? $this->findById($id) : null;
    }

    /**
     * $name must be a valid php class name (class does not have to exist)
     *
     * returns > 0 on success
     */
    public function createCategory(string $name): int {
        return $this->pg()->scalarex("
            insert into report_auto_category (name) values ($1)
            returning id_report_auto_category
        ", $name);
    }

    public function create(
        string $name,
        string $description,
        string|null $category = null,
    ): ReportAutoTypeInstance {
        if ($category) {
            $catId = $this->findCategory($category);
            if (!$catId) {
                $catId = $this->createCategory($category);
            }
        } else {
            $catId = null;
        }
        return new ReportAutoTypeInstance(
            $this->pg()->scalarex("
                insert into report_auto_type
                    (name, id_report_auto_category, description)
                values
                    ($1,   $2,                      $3)
                returning id_report_auto_type
                ", $name, $catId, $description
            )
        );
    }

    protected function findCategory(string $name): int|null {
        return $this->pg()->scalarex("
            select id_report_auto_category from report_auto_category where name = $1
            ", $name
        );
    }
}
