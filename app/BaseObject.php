<?php

namespace Gazelle;

abstract class BaseObject extends Base {

    protected $id;

    /* used for handling updates */
    protected $updateField = [];
    protected $updateFieldPassThru = [];
    protected $updateFieldRaw = [];

    public function __construct(int $id) {
        parent::__construct();
        $this->id = $id;
    }

    abstract public function tableName(): string;
    abstract public function flush();

    public function id(): int {
        return $this->id;
    }

    public function dirty(): bool {
        return !empty($this->updateField)
            || !empty($this->updateFieldPassThru)
            || !empty($this->updateFieldRaw);
    }

    public function setUpdate(string $field, $value) {
        $this->updateField[$field] = $value;
        return $this;
    }

    public function setUpdatePassThru(string $field, $value) {
        $this->updateFieldPassThru[$field] = $value;
        return $this;
    }

    public function setUpdateRaw(string $field) {
        $this->updateFieldRaw[] = $field;
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
            array_map(fn($f) => "$f = ?", array_keys($this->updateField)),
            array_keys($this->updateFieldPassThru),
            $this->updateFieldRaw
        ));
        $args = array_merge(
            array_values($this->updateField),
            array_values($this->updateFieldPassThru)
        );
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
