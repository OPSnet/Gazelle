<?php

namespace Gazelle;

abstract class BaseObject extends Base {

    protected int $id;

    /* used for handling updates */
    protected array $updateField = [];

    public function __construct(int $id) {
        $this->id = $id;
    }

    abstract public function tableName(): string;
    abstract public function flush();
    abstract public function url(): string;
    abstract public function link(): string;

    public function id(): int {
        return $this->id;
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
