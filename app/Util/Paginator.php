<?php

namespace Gazelle\Util;

class Paginator {
    protected array $remove = []; // parameters to strip out of URIs (e.g. postid for comments)
    protected string $anchor = '';
    protected string $param = '';
    protected string $linkbox;
    protected int $total = 0;
    protected int $linkCount = 10;

    /**
     * Calculat offset and limit for SQL pagination,
     * based on the number of rows per page and the current page (usually $_GET['page'])
     *
     * @param int $perPage Results to show per page
     * @param int $page current page
     */
    public function __construct(
        protected readonly int $perPage,
        protected int $page
    ) {}

    public function page(): int {
        return $this->page;
    }

    public function limit(): int {
        return $this->perPage;
    }

    public function offset(): int {
        return $this->perPage * ($this->page - 1);
    }

    public function total(): int {
        return $this->total;
    }

    public function pages(): int {
        return (int)ceil($this->total / $this->perPage);
    }

    public function setAnchor(string $anchor): Paginator {
        $this->anchor = '#' . $anchor;
        return $this;
    }

    public function setParam(string $param): Paginator {
        $this->param = '&amp;' . $param;
        return $this;
    }

    public function setTotal(int $total): Paginator {
        $this->total = $total;
        return $this;
    }

    public function removeParam(string $param): void {
        $this->remove[] = $param;
    }

    public function linkbox(): string {
        if (isset($this->linkbox)) {
            return $this->linkbox;
        }
        $pageCount = 0;
        $this->linkbox = '';

        $uri = preg_replace('/[?&]page=\d+/', '', $_SERVER['REQUEST_URI']);
        foreach ($this->remove as $param) {
            /* page?param=1&keep=2 => page?keep=2
             * page?keep=2&param=1 => page?keep=2
             * page?keep=3&param=2&also=3 => page?keep=3&also=3
             */
            $uri = preg_replace("/(?:(?<=\?)$param=[^&]+&?|&$param=[^&]+(?:(?=&)|$))/", '', $uri);
        }
        $uri = str_replace('&', '&amp;', $uri);
        if (!str_contains($uri, '?')) {
            $uri .= '?';
        }

        if ($this->total > 0) {
            $this->page = min($this->page, (int)ceil($this->total / $this->perPage));
            $pageCount = (int)ceil($this->total / $this->perPage);

            if ($pageCount <= $this->linkCount) {
                $firstPage = 1;
                $lastPage = $pageCount;
            } else {
                $firstPage = $this->page - (int)round($this->linkCount / 2);
                if ($firstPage <= 0) {
                    $firstPage = 1;
                } else {
                    if ($firstPage >= $pageCount - $this->linkCount) {
                        $firstPage = max(1, $pageCount - $this->linkCount);
                    }
                }
                $lastPage = $this->linkCount + $firstPage;
            }
            if ($firstPage === $lastPage) {
                $this->linkbox = '';
                return $this->linkbox;
            }

            if ($this->page > 1) {
                $this->linkbox = "<a href=\"{$uri}&amp;page=1{$this->param}{$this->anchor}\"><strong>&laquo; First</strong></a> "
                    . "<a href=\"{$uri}&amp;page=" . ($this->page - 1) . $this->param . $this->anchor . '" class="pager_prev"><strong>&lsaquo; Prev</strong></a> | ';
            }

            for ($i = $firstPage; $i <= $lastPage; $i++) {
                if ($i != $this->page) {
                    $this->linkbox .= "<a href=\"{$uri}&amp;page=$i{$this->param}{$this->anchor}\">";
                }
                $this->linkbox .= '<strong>';
                $firstEntry = (($i - 1) * $this->perPage) + 1;
                if ($i * $this->perPage > $this->total) {
                    if ($firstEntry == $this->total) {
                        $this->linkbox .= $this->total;
                    } else {
                        $this->linkbox .= "$firstEntry-" . $this->total;
                    }
                } else {
                    $this->linkbox .= "$firstEntry-" . ($i * $this->perPage);
                }
                $this->linkbox .= '</strong>';

                if ($i != $this->page) {
                    $this->linkbox .= '</a>';
                }
                if ($i < $lastPage) {
                    $this->linkbox .= ' | ';
                }
                $this->linkCount--;
            }

            if ($this->page && $this->page < $pageCount) {
                $this->linkbox .= " | <a href=\"{$uri}&amp;page=" . ($this->page + 1) . $this->param . $this->anchor
                    . '" class="pager_next"><strong>Next &rsaquo;</strong></a>'
                    . " <a href=\"{$uri}&amp;page=$pageCount\"><strong> Last &raquo;</strong></a>";
            }
        }
        if (strlen($this->linkbox)) {
            $anchorName = $this->anchor ? ('<a name="' . substr($this->anchor, 1) . '"></a>') : '';
            $this->linkbox = "<div class=\"linkbox\">$anchorName{$this->linkbox}</div>";
        }
        return $this->linkbox;
    }

    // used for pagination of peer/snatch/download lists on torrentdetails.php
    public function linkboxJS(string $action, int $torrentId): string {
        if ($this->total < $this->perPage) {
            return '';
        }
        $page = range(1, (int)ceil($this->total / $this->perPage));
        $link = [];
        foreach ($page as $p) {
            $link[] = ($p === $this->page)
                ? $p
                : "<a href=\"#\" onclick=\"$action($torrentId, $p); return false;\">$p</a>";
        }
        return '<div class="linkbox">' . implode(' &sdot; ', $link) . '</div>';
    }
}
