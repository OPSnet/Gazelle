<?php

declare(strict_types=1);

namespace Gazelle;

class Counter {
    use Pg;

    protected array $info;

    public function __construct(
        protected readonly string $name
    ) {}

    public function flush(): static {
        unset($this->info);
        return $this;
    }

    public function info(): array {
        return $this->info ??= $this->pg()->rowAssocex("
            select description, value from counter where name = $1
            ", $this->name
        );
    }

    public function description(): string {
        return $this->info()['description'];
    }

    public function name(): string {
        return $this->name;
    }

    public function value(): int {
        return $this->info()['value'];
    }

    public function increment(): int {
        $new = $this->pg()->scalarex("
            update counter set
                value = value + 1
            where name = $1
            returning value
            ", $this->name
        );
        $this->flush();
        return $new;
    }
}
