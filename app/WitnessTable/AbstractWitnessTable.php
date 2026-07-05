<?php

namespace Gazelle\WitnessTable;

use Gazelle\Enum\SourceDB;
use Gazelle\User;

abstract class AbstractWitnessTable extends \Gazelle\Base {
    protected const string WITNESS_KEY = 'witness_%s_%d';

    abstract protected function sourceDb(): SourceDB;

    abstract protected function reference(): string;

    abstract protected function refIdColumn(): string;

    abstract protected function tableName(): string;

    abstract protected function idColumn(): string;

    abstract protected function valueColumn(): string;

    abstract public function witness(User $user): bool;

    public function flushUser(User $user): static {
        self::$cache->delete_value(sprintf(self::WITNESS_KEY, $this->tableName(), $user->id));
        return $this;
    }

    protected function latestValue(): ?int {
        $id = $this->sourceDb() === SourceDB::mysql
            ? self::$db->scalar("SELECT max({$this->refIdColumn()}) FROM {$this->reference()}")
            : $this->pg()->scalarex("select max({$this->refIdColumn()}) from {$this->reference()}");
        return $id ? (int)$id : null;
    }

    protected function witnessValue(User $user): bool {
        $latest = $this->latestValue();

        self::$db->prepared_query("
            INSERT INTO {$this->tableName()}
            ({$this->idColumn()}, {$this->valueColumn()}) VALUES (?, ?)
            ON DUPLICATE KEY UPDATE {$this->valueColumn()} = ?
            ", $user->id, $latest, $latest
        );
        $success = self::$db->affected_rows() !== 0;
        if ($success) {
            $this->flushUser($user);
        }
        return $success;
    }

    protected function witnessDate(User $user): bool {
        self::$db->prepared_query("
            INSERT INTO {$this->tableName()}
            ({$this->idColumn()}) VALUES (?)
            ON DUPLICATE KEY UPDATE {$this->valueColumn()} = now()
            ", $user->id
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
    public function lastRead(User $user): ?int {
        $key = sprintf(self::WITNESS_KEY, $this->tableName(), $user->id);
        $id = self::$cache->get_value($key);
        if ($id === false) {
            $id = self::$db->scalar("
                SELECT {$this->valueColumn()} FROM {$this->tableName()} WHERE {$this->idColumn()} = ?
                ", $user->id
            );
            self::$cache->cache_value($key, $id, 180);
        }
        return $id ? (int)$id : null;
    }
}
