<?php

namespace Gazelle\WitnessTable;

abstract class AbstractWitnessTable extends \Gazelle\Base {
    abstract protected function tableName();
    abstract protected function idColumn();
    abstract protected function valueColumn();
    abstract public function witness(int $id);

    protected function witnessValue(int $id, int $value) {
        $this->db->prepared_query($sql = "
            INSERT INTO {$this->tableName()}
            ({$this->idColumn()}, {$this->valueColumn()}) VALUES (?, ?)
            ON DUPLICATE KEY UPDATE {$this->valueColumn()} = ?
            ", $id, $value, $value
        );
        return $this;
    }

    protected function witnessDate(int $id) {
        $this->db->prepared_query("
            INSERT INTO {$this->tableName()}
            ({$this->idColumn()}) VALUES (?)
            ON DUPLICATE KEY UPDATE {$this->valueColumn()} = now()
            ", $id
        );
        return $this;
    }
}
