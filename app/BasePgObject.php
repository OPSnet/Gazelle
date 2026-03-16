<?php

namespace Gazelle;

abstract class BasePgObject extends BaseObject {
    public function modify(): bool {
        if (!$this->dirty()) {
            return false;
        }
        $counter = 1;
        $set = implode(
            ', ',
            array_map(
                function ($f) use (&$counter) { return "$f = $" . $counter++; },
                array_keys($this->updateField)
            )
        );
        $args = [...array_values($this->updateField)];
        $args[] = $this->id();
        $rowCount = $this->pg()->query(
            "update /* BasePgObject */ " . static::tableName
                . " set $set where " . static::pkName . ' = $' . $counter,
            ...$args
        );
        $success = ($rowCount === 1);
        if ($success) {
            $this->flush();
        }
        return $success;
    }

    /**
     * Remove an object. If there is no caching, this is all you need.
     * It will blow up if there are non-cascading foreign keys still present,
     * but that is probably what you want. Clean those first explicitly
     * before calling this.
     */
    public function remove(): int {
        $affected = $this->pg()->query(
            "DELETE /* BasePgObject */ FROM "
                . static::tableName
                . " WHERE "
                . static::pkName
                . ' = $1',
            $this->id
        );
        $this->flush();
        return $affected;
    }
}
