<?php

namespace Gazelle\WitnessTable;

abstract class AbstractWitnessTable extends \Gazelle\Base {
    abstract protected function reference();
    abstract protected function tableName();
    abstract protected function idColumn();
    abstract protected function valueColumn();
    abstract public function witness(int $id): bool;

    protected function latestValue(): ?int {
        return $this->db->scalar("SELECT max(ID) FROM {$this->reference()}");
    }

    protected function witnessValue(int $userId): bool {
        $latest = $this->latestValue();
        $this->db->prepared_query($sql = "
            INSERT INTO {$this->tableName()}
            ({$this->idColumn()}, {$this->valueColumn()}) VALUES (?, ?)
            ON DUPLICATE KEY UPDATE {$this->valueColumn()} = ?
            ", $userId, $latest, $latest
        );
        return $this->db->affected_rows() !== 0;
    }

    protected function witnessDate(int $userId): bool {
        $this->db->prepared_query("
            INSERT INTO {$this->tableName()}
            ({$this->idColumn()}) VALUES (?)
            ON DUPLICATE KEY UPDATE {$this->valueColumn()} = now()
            ", $userId
        );
        return $this->db->affected_rows() !== 0;
    }

    /**
     * Return the ID of the most recent unread article
     *
     * @param int user ID of the reader
     * @return int article ID or null if no article has been read
     */
    public function lastRead(int $userId): ?int {
        return $this->db->scalar("
            SELECT {$this->valueColumn()} FROM {$this->tableName()} WHERE {$this->idColumn()} = ?
            ", $userId
        );
    }
}
