<?php

namespace Gazelle\WitnessTable;

abstract class AbstractWitnessTable extends \Gazelle\Base {
    abstract protected function reference();
    abstract protected function tableName();
    abstract protected function idColumn();
    abstract protected function valueColumn();
    abstract public function witness(int $id): bool;

    protected function latestValue(): ?int {
        return self::$db->scalar("SELECT max(ID) FROM {$this->reference()}");
    }

    protected function witnessValue(int $userId): bool {
        $latest = $this->latestValue();
        self::$db->prepared_query($sql = "
            INSERT INTO {$this->tableName()}
            ({$this->idColumn()}, {$this->valueColumn()}) VALUES (?, ?)
            ON DUPLICATE KEY UPDATE {$this->valueColumn()} = ?
            ", $userId, $latest, $latest
        );
        $success = self::$db->affected_rows() !== 0;
        if ($success) {
            self::$cache->delete_value("u_$userId");
        }
        return $success;
    }

    protected function witnessDate(int $userId): bool {
        self::$db->prepared_query("
            INSERT INTO {$this->tableName()}
            ({$this->idColumn()}) VALUES (?)
            ON DUPLICATE KEY UPDATE {$this->valueColumn()} = now()
            ", $userId
        );
        $success = self::$db->affected_rows() !== 0;
        if ($success) {
            self::$cache->delete_value("u_$userId");
        }
        return $success;
    }

    /**
     * Return the ID of the most recent unread article
     *
     * @param int $userId user ID of the reader
     * @return null|int article ID or null if no article has been read
     */
    public function lastRead(int $userId): ?int {
        return self::$db->scalar("
            SELECT {$this->valueColumn()} FROM {$this->tableName()} WHERE {$this->idColumn()} = ?
            ", $userId
        );
    }
}
