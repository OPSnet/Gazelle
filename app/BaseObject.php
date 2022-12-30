<?php

namespace Gazelle;

abstract class BaseObject extends Base {
    /* used for handling updates */
    protected array $updateField;

    // FIXME: StaffPM breaks readonly-ness due to inheritance
    public function __construct(
        protected int $id,
    ) {}

    abstract public function flush(): BaseObject;
    abstract public function link(): string;
    abstract public function location(): string;
    abstract public function tableName(): string;

    public function id(): int {
        return $this->id;
    }

    public function url(): string {
        return htmlentities($this->location());
    }

    public function pkName(): string {
        return "ID";
    }

    public function dirty(): bool {
        return !empty($this->updateField);
    }

    public function setUpdate(string $field, $value) {
        $this->updateField[$field] = $value;
        return $this;
    }

    public function field(string $field) {
        return $this->updateField[$field] ?? null;
    }

    public function modify(): bool {
        if (!$this->dirty()) {
            return false;
        }
        $set = implode(', ', array_merge(
            array_map(fn($f) => "$f = ?", array_keys($this->updateField))
        ));
        $args = array_merge(
            array_values($this->updateField),
        );
        $args[] = $this->id;
        self::$db->prepared_query(
            "UPDATE {$this->tableName()} SET $set WHERE {$this->pkName()} = ?",
            ...$args
        );
        $success = (self::$db->affected_rows() === 1);
        if ($success) {
            $this->flush();
        }
        return $success;
    }
}
