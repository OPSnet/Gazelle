<?php

namespace Gazelle;

abstract class BaseObject extends Base {
    protected array $updateField; // used to store field updates
    protected User  $updateUser;  // user performing the updates

    protected array|null $info;

    // FIXME: StaffPM breaks readonly-ness due to inheritance
    public function __construct(
        protected int $id,
    ) {}

    abstract public function flush(): BaseObject;
    abstract public function link(): string;
    abstract public function location(): string;
    abstract public function tableName(): string;

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

    public function pkName(): string {
        return "ID";
    }

    public function dirty(): bool {
        return !empty($this->updateField);
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
    public function setUpdate(string $field, array|bool|int|float|string|null $value): mixed {
        $this->updateField[$field] = $value;
        return $this;
    }

    /**
     * Fetch the value of a table field to be updated. Returns null if
     * either the field does not exists, or it does and is set to null :)
     * If ever this is a problem, you can always clearField() which
     * guarantees the the field will no longer be present.
     */
    public function field(string $field): array|bool|int|float|string|null {
        return $this->updateField[$field] ?? null;
    }

    /**
     * Remove a field from the update. This is useful in a derived class
     * when an auxillary table needs to be update with this value.
     * @return array|bool|int|float|string|null the contents of the field, or null
     */
    public function clearField(string $field): array|bool|int|float|string|null {
        if (isset($this->updateField[$field])) {
            $value = $this->updateField[$field];
            unset($this->updateField[$field]);
            return $value;
        }
        return null;
    }

    public function modify(): bool {
        if (!$this->dirty()) {
            return false;
        }
        $set = implode(', ', [...array_map(fn($f) => "$f = ?", array_keys($this->updateField))]);
        $args = [...array_values($this->updateField)];
        $args[] = $this->id();
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
