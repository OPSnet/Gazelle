<?php

namespace Gazelle\Request;

use Gazelle\Pg;

abstract class AbstractValue {
    use Pg;

    protected array $label;

    public function __construct(
        protected bool  $all  = false,
        protected array $list = [],
    ) {
        $legal = $this->legal();
        $this->label = [];
        foreach ($this->list as $value) {
            $offset = array_search($value, $legal);
            if ($offset !== false) {
                $this->label[$offset] = $value;
            }
        }
        ksort($this->label);
    }

    abstract protected function legal(): array;

    public function all(): bool {
        return $this->all || in_array(count($this->label), [0, count($this->legal())]);
    }

    public function isValid(): bool {
        return $this->all() || count($this->label) > 0;
    }

    public function exists(string $value): bool {
        return $this->all() || array_search($value, $this->label) !== false;
    }

    public function dbList(): array|null {
        return $this->all() ? null : array_values($this->label);
    }

    public function dbValue(): string {
        return $this->all() || count($this->label) == count($this->legal())
            ? '{}'
            : $this->pg()->arrayConverter()->output(array_values($this->label));
    }

    public function display(): string {
        return $this->all || count($this->label) == count($this->legal())
            ? 'Any'
            : implode(', ', array_values($this->label));
    }

    public function legacyDisplay(): string {
        return $this->all || count($this->label) == count($this->legal())
            ? 'Any'
            : implode('|', $this->label);
    }
}
