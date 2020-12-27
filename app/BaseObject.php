<?php

namespace Gazelle;

abstract class BaseObject extends Base {

    protected $id;

    /* used for handling updates */
    protected $updateField = [];

    public function __construct(int $id) {
        parent::__construct();
        $this->id = $id;
    }

    abstract public function tableName(): string;
    abstract public function flush();

    public function id(): int {
        return $this->id;
    }

    public function setUpdate(string $field, $value) {
        $this->updateField[$field] = $value;
        return $this;
    }

    public function field(string $field) {
        return $this->updateField[$field] ?? null;
    }

    public function modify(): bool {
        if (!$this->updateField) {
            return false;
        }
        $set = implode(', ', array_map(function ($f) { return "$f = ?"; }, array_keys($this->updateField)));
        $args = array_values($this->updateField);
        $args[] = $this->id;
        $this->db->prepared_query(
            "UPDATE " . $this->tableName() . " SET
                $set WHERE ID = ?
            ", ...$args
        );
        $success = ($this->db->affected_rows() === 1);
        if ($success) {
            $this->flush();
        }
        return $success;
    }
}
