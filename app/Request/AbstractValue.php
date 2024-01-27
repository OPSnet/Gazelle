<?php

namespace Gazelle\Request;

abstract class AbstractValue {
    protected array $label;
    public function __construct(
        protected bool  $all  = false,
        protected array $list = [],
    ) {
        $legal = $this->legal();
        $this->label = [];
        foreach ($this->list as $offset) {
            if (isset($legal[$offset])) {
                $this->label[$offset] = $legal[$offset];
            }
        }
        ksort($this->label);
    }

    abstract protected function legal(): array;

    public function all(): bool {
        return $this->all || count($this->label) == count($this->legal());
    }

    public function isValid(): bool {
        return $this->all || count($this->label);
    }

    public function exists(string $label): bool {
        return $this->all || array_search($label, $this->label) !== false;
    }

    public function dbValue(): ?string {
        return $this->all || count($this->label) == count($this->legal())
            ? 'Any'
            : implode('|', $this->label);
    }
}
