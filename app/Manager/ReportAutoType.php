<?php

namespace Gazelle\Manager;

class ReportAutoType extends \Gazelle\BaseManager {
    use \Gazelle\Pg;

    protected array $reportTypes = [];

    public function findById(int $id): ?\Gazelle\ReportAuto\Type {
        if (isset($this->reportTypes[$id])) {
            return $this->reportTypes[$id];
        }
        $type = new \Gazelle\ReportAuto\Type($id);
        try {
            $type->name();
        } catch (\TypeError) {
            return null;
        }
        $this->reportTypes[$id] = $type;
        return $type;
    }

    public function findByName(string $name): ?\Gazelle\ReportAuto\Type {
        $id = $this->pg()->scalar("
                SELECT id_report_auto_type FROM report_auto_type WHERE name = ?
            ", $name);
        return $id ? $this->findById($id) : null;
    }

    /**
     * $name must be a valid php class name (class does not have to exist)
     *
     * returns > 0 on success, null otherwise (including if the category already exists)
     */
    public function createCategory(string $name): ?int {
        return $this->pg()->scalar("
            INSERT INTO report_auto_category (name) VALUES (?)
            ON CONFLICT (name) DO NOTHING
            RETURNING id_report_auto_category", // does not work for DO NOTHING
        $name);
    }

    /**
     * returns newly created type, null on error or if type with name already exists
     */
    public function create(string $name, string $description, ?string $category = null): ?\Gazelle\ReportAuto\Type {
        if ($category) {
            $catId = $this->findCategory($category);
            if (!$catId) {
                $catId = $this->createCategory($category);
                if (!$catId) {  // probably invalid name
                    return null;
                }
            }
        } else {
            $catId = null;
        }
        try {
            return $this->findById($this->pg()->scalar("
                INSERT INTO report_auto_type
                    (name, id_report_auto_category, description)
                VALUES
                    (?,    ?,                       ?)
                RETURNING id_report_auto_type
            ", $name, $catId, $description));
        } catch (\PDOException) {
            return null;
        }
    }

    protected function findCategory(string $name): ?int {
        return $this->pg()->scalar("
            SELECT id_report_auto_category FROM report_auto_category WHERE name = ?
        ", $name);
    }
}
