<?php

namespace Gazelle\Util;

class Paginator {
    protected $perPage = 25;
    protected $page = 1;

    /**
     * Calculat offset and limit for SQL pagination,
     * based on the number of rows per page and the current page (usually $_GET['page'])
     *
     * @param int $perPage Results to show per page
     * @param int $page current page
     */
    public function __construct(int $perPage, int $page) {
        $this->perPage = $perPage;
        $this->page = $page;
    }

    public function page(): int {
        return $this->page;
    }

    public function limit(): int {
        return $this->perPage;
    }

    public function offset(): int {
        return $this->perPage * ($this->page - 1);
    }
}
