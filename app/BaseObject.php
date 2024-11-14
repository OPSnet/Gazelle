<?php

namespace Gazelle;

abstract class BaseObject extends Base {
    public const tableName = 'abstract';
    public const pkName    = 'ID';

    protected array $updateField; // used to store field updates
    protected array $nowField;    // used to store fields that must be updated to now()
    protected User  $updateUser;  // user performing the updates

    protected array $info;

    // FIXME: StaffPM breaks readonly-ness due to inheritance
    public function __construct(
        protected int $id,
    ) {}

    abstract public function flush(): static;

    abstract public function link(): string;

    abstract public function location(): string;

    public function id(): int {
        return $this->id;
    }

    public function publicLocation(): string {
        return SITE_URL . '/' . $this->location();
    }

    public function publicUrl(string|null $param = null): string {
        return SITE_URL . '/' . $this->url($param);
    }

    public function url(string|null $param = null): string {
        $location = $this->location();
        if (isset($param)) {
            $location = preg_replace('/#.*$/', '', $location);
            $location .= str_contains($location, '?') ? "&$param" : "?$param";
        }
        return htmlentities($location);
    }

    public function dirty(): bool {
        return isset($this->updateField) || isset($this->nowField);
    }

    /**
     * If an auxilliary update needs a User (e.g. for writing to the log)
     * this method is used to supply the user.
     */
    public function setUpdateUser(User $user): mixed {
        $this->updateUser = $user;
        return $this;
    }

    /**
     * Arrays and Gazelle objects can be passed, but it is expected that
     * the derived class will deal with or pre-process the contents so
     * the modify() method can do its thing.
     */
    public function setField(string $field, mixed $value): mixed {
        $this->updateField[$field] = $value;
        return $this;
    }

    /**
     * Fields that need to set to now() when updated.
     */
    public function setFieldNow(string $field): mixed {
        $this->nowField[$field] = true;
        return $this;
    }

    /**
     * Fetch the value of a table field to be updated. Returns null if
     * either the field does not exists, or it does and is set to null.
     * In this case you can call nullField() which will return true if
     * the field is present and set to null.
     */
    public function field(string $field): mixed {
        return $this->updateField[$field] ?? null;
    }

    public function nowField(string $field): mixed {
        return $this->nowField[$field] ?? null;
    }

    public function nullField(string $field): bool {
        return isset($this->updateField)
            && in_array($field, array_keys($this->updateField))
            && is_null($this->updateField[$field]);
    }

    /**
     * Remove a field from the update. This is useful in a derived class
     * when an auxillary table needs to be update with this value.
     * If the field is to be set to now(), the name of the field is returned.
     * @return mixed the contents of the field, the name of the field, or null
     */
    public function clearField(string $field): mixed {
        if ($this->nowField($field)) {
            unset($this->nowField[$field]);
            if (empty($this->nowField)) {
                // if the array is merely empty, the dirty() method will return true
                unset($this->nowField);
            }
            return $field;
        }
        if (isset($this->updateField[$field]) || $this->nullField($field)) {
            $value = $this->updateField[$field];
            unset($this->updateField[$field]);
            if (empty($this->updateField)) {
                unset($this->updateField);
            }
            return $value;
        }
        return null;
    }

    public function modify(): bool {
        if (!$this->dirty()) {
            return false;
        }
        if (!isset($this->updateField)) {
            $set = [];
            $args = [];
        } else {
            $set = array_map(fn($f) => "$f = ?", array_keys($this->updateField));
            $args = array_values($this->updateField);
        }
        if (isset($this->nowField)) {
            foreach (array_keys($this->nowField) as $field) {
                $set[] = "$field = now()";
            }
        }
        $args[] = $this->id();
        self::$db->prepared_query(
            "UPDATE " . static::tableName . " SET " . implode(', ', $set) . " WHERE " . static::pkName . " = ?",
            ...$args
        );
        $success = (self::$db->affected_rows() === 1);
        unset($this->updateField);
        unset($this->nowField);
        if ($success) {
            $this->flush();
        }
        return $success;
    }
}
