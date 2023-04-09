<?php

namespace Gazelle;

abstract class BaseObject extends Base {
    /* used for handling updates */
    protected array $updateField;

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

    public function setUpdate(string $field, bool|int|float|string|null $value): mixed {
        $this->updateField[$field] = $value;
        return $this;
    }

    public function field(string $field): bool|int|float|string|null {
        return $this->updateField[$field] ?? null;
    }

    public function modify(): bool {
        if (!$this->dirty()) {
            return false;
        }
        $set = implode(', ', [...array_map(fn($f) => "$f = ?", array_keys($this->updateField))]);
        $args = [...array_values($this->updateField)];
        $args[] = $this->id;
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
