<?php

namespace Gazelle\WitnessTable;

abstract class AbstractWitnessTable extends \Gazelle\Base {
    abstract protected function reference(): string;
    abstract protected function tableName(): string;
    abstract protected function idColumn(): string;
    abstract protected function valueColumn(): string;
    abstract public function witness(\Gazelle\User $user): bool;

    protected function latestValue(): ?int {
        $id = self::$db->scalar("SELECT max(ID) FROM {$this->reference()}");
        return $id ? (int)$id : null;
    }

    protected function witnessValue(\Gazelle\User $user): bool {
        $latest = $this->latestValue();
        self::$db->prepared_query($sql = "
            INSERT INTO {$this->tableName()}
            ({$this->idColumn()}, {$this->valueColumn()}) VALUES (?, ?)
            ON DUPLICATE KEY UPDATE {$this->valueColumn()} = ?
            ", $user->id(), $latest, $latest
        );
        $success = self::$db->affected_rows() !== 0;
        if ($success) {
            $user->flush();
        }
        return $success;
    }

    protected function witnessDate(\Gazelle\User $user): bool {
        self::$db->prepared_query("
            INSERT INTO {$this->tableName()}
            ({$this->idColumn()}) VALUES (?)
            ON DUPLICATE KEY UPDATE {$this->valueColumn()} = now()
            ", $user->id()
        );
        $success = self::$db->affected_rows() !== 0;
        if ($success) {
            $user->flush();
        }
        return $success;
    }

    /**
     * Return the ID of the most recent unread article
     *
     * @return null|int article ID or null if no article has been read
     */
    public function lastRead(\Gazelle\User $user): ?int {
        $id = self::$db->scalar("
            SELECT {$this->valueColumn()} FROM {$this->tableName()} WHERE {$this->idColumn()} = ?
            ", $user->id()
        );
        return $id ? (int)$id : null;
    }
}
