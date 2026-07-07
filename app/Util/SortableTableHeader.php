<?php

namespace Gazelle\Util;

class SortableTableHeader {
    private const SORT_DIRS = ['asc' => 'desc', 'desc' => 'asc', '' => ''];

    private readonly string $currentSortKey;
    private readonly string $currentSortDir;

    /**
     * SortableTableHeader constructor.
     * Label map array is structured like this example:
     * [
     *   'seeders' => [
     *     'dbColumn'    => 'columnName',
     *     'defaultSort' => 'desc',
     *     'text'        => 'Column Display Text',
     *     'alias'       => 'c1',
     *   ],
     *   'size' => [
     *     'dbColumn'    => 'columnName',
     *     'defaultSort' => 'desc',
     *     'text'        => 'Column Display Text',
     *     'alias'       => 'c2',
     *   ],
     * ]
     * The alias fields are optional, but useful when the columns are entirely dynameic
     * Items missing a 'text' value cannot be output.
     *
     * @param array $arrowMap sort direction => symbol to output
     */
    public function __construct(
        string $defaultSortKey,
        private readonly array $labelMap,
        private readonly array $arrowMap = ['asc' => '↓', 'desc' => '↑', '' => ''],
        array $request = [],
    ) {
        if ($request === []) {
            // Since we can't have expressions as default values in the param list...
            $request = $_GET;
        }
        $this->currentSortKey = isset($labelMap[$request['order'] ?? ''])
            ? $request['order']
            : $defaultSortKey;
        $this->currentSortDir =
            (!isset($request['sort']) || $request['sort'] === ($this->current()['defaultSort'] ?? ''))
            ? $this->current()['defaultSort']
            : self::SORT_DIRS[$this->current()['defaultSort']];
    }

    public function alias(string $columnKey): string {
        $alias = array_first(array_values(array_filter(
            $this->labelMap,
            fn ($m) => $m['alias'] === $columnKey,
        )));
        return is_array($alias) ? ($alias['text'] ?? "err:$columnKey") : '';
    }

    public function aliasField(string $columnKey): string {
        $field = array_keys(array_filter(
            $this->labelMap,
            fn ($m) => $m['alias'] === $columnKey,
        ));
        return $field !== [] ? (string)array_first($field) : '';
    }

    public function emit(string $columnKey): string {
        $columnData = $this->labelMap[$columnKey];
        if ($columnData === []) {
            // Fail if column does not exist
            return "err:$columnKey";
        }
        if (!isset(self::SORT_DIRS[$columnData['defaultSort']], self::SORT_DIRS[$this->currentSortDir])) {
            // Fail gracefully if misconfigured
            return $columnData['text'];
        }

        $isCurrent  = ($columnKey === $this->currentSortKey);
        return sprintf(
            '<a href="?%s">%s</a> %s',
            get_url(
                ['page'],
                true,
                false,
                [
                    'order' => $columnKey,
                    'sort'  => $isCurrent
                        ? self::SORT_DIRS[$this->currentSortDir]
                        : $columnData['defaultSort'],
                ],
            ),
            $columnData['text'],
            $isCurrent ? $this->arrowMap[$this->currentSortDir] : '',
        );
    }

    public function current(): array {
        return $this->labelMap[$this->currentSortKey] ?? [];
    }

    public function orderKeyList(): array {
        return array_keys($this->labelMap);
    }

    public function orderBy(): ?string {
        return $this->current()['dbColumn'] ?? null;
    }

    public function dir(): string {
        return $this->currentSortDir;
    }

    public function orderKey(): string {
        return $this->currentSortKey;
    }
}
