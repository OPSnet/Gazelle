<?php

namespace Gazelle\Util;

class Paginator {
    protected array $remove = []; // parameters to strip out of URIs (e.g. postid for comments)
    protected string $anchor = '';
    protected array $param = [];
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

    public function perPage(): int {
        return $this->perPage;
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

    public function setAnchor(string $anchor): static {
        $this->anchor = '#' . $anchor;
        return $this;
    }

    public function setParam(string $key, string $value): static {
        $this->param[$key] = $value;
        return $this;
    }

    public function setTotal(int $total): static {
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
        $this->linkbox = '';

        $uri = (string)preg_replace('/[?&]page=\d+/', '', $_SERVER['REQUEST_URI']);
        foreach ($this->remove as $param) {
            /* page?param=1&keep=2 => page?keep=2
             * page?keep=2&param=1 => page?keep=2
             * page?keep=3&param=2&also=3 => page?keep=3&also=3
             */
            $uri = (string)preg_replace("/(?:(?<=\?)$param=[^&]+&?|&$param=[^&]+(?:(?=&)|$))/", '', $uri);
        }
        $uri = str_replace('&', '&amp;', rtrim($uri, '?'));
        $uri .= str_contains($uri, '?') ? '&amp;' : '?';

        if ($this->total > 0) {
            $pageCount = $this->pages();
            $this->page = min($this->page, $pageCount);

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

            $paramList = $this->param ? ('&amp;' . http_build_query($this->param, '', '&amp;')) : '';
            if ($this->page > 1) {
                $this->linkbox = "<a href=\"{$uri}page=1{$paramList}{$this->anchor}\"><strong>&laquo; First</strong></a> "
                    . "<a href=\"{$uri}page=" . ($this->page - 1) . $paramList . $this->anchor . '" class="pager_prev"><strong>&lsaquo; Prev</strong></a> | ';
            }

            for ($i = $firstPage; $i <= $lastPage; $i++) {
                if ($i != $this->page) {
                    $this->linkbox .= "<a href=\"{$uri}page=$i{$paramList}{$this->anchor}\">";
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
                $this->linkbox .= " | <a href=\"{$uri}page=" . ($this->page + 1) . $paramList . $this->anchor
                    . '" class="pager_next"><strong>Next &rsaquo;</strong></a>'
                    . " <a href=\"{$uri}page=" . $pageCount . $paramList . $this->anchor
                    . "\"><strong> Last &raquo;</strong></a>";
            }
        }
        if (strlen($this->linkbox)) {
            $anchorName = $this->anchor ? ('<a name="' . substr($this->anchor, 1) . '"></a>') : '';
            $this->linkbox = "<div class=\"linkbox\">$anchorName{$this->linkbox}</div>";
        }
        return $this->linkbox;
    }
}
