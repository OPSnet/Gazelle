<?php

declare(strict_types=1);

namespace Gazelle\Search\Comment;

use Gazelle\User;

abstract class AbstractComment extends \Gazelle\Base {
    protected array $args;
    protected array $condition;
    protected string $join;

    /**
     * This method returns then name of a table that MUST be aliased to src,
     * in order to generate a generic 'from' clause in an SQL statement. The
     * query is executed by Postgresql, so non-migrated Mysql tables are
     * referenced in the relay schema.
     * e.g.: 'relay.collages src'
     */
    abstract public function table(): string;

    abstract public function idField(): string;

    abstract public function nameField(): string;

    abstract public function condition(): array;

    abstract public function args(): array;

    abstract public function title(): string;

    public function join(): string {
        return $this->join ?? '/* no join */';
    }

    public function linkList(): array {
        return [];
    }
}
