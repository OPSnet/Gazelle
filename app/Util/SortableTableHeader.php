<?php

namespace Gazelle\Util;

use Format;

class SortableTableHeader {
    private const SORT_DIRS = ['asc' => 'desc', 'desc' => 'asc', '' => ''];

    /** @var array */
    private $labelMap;

    /** @var string */
    private $currentSortKey;

    /** @var string */
    private $currentSortDir;

    /** @var array */
    private $arrowMap;

    /**
     * SortableTableHeader constructor.
     * Label map array is structured like this example:
     * [
     *   'seeders' => [
     *     'dbColumn'    => 'columnName',
     *     'defaultSort' => 'desc',
     *     'text'        => 'Column Display Text',
     *   ],
     *   'size' => [
     *     'dbColumn'    => 'columnName',
     *     'defaultSort' => 'desc',
     *     'text'        => 'Column Display Text',
     *   ],
     * ]
     * Items missing a 'text' value cannot be output.
     *
     * @param string $defaultSortKey
     * @param array $labelMap
     * @param array $arrowMap sort direction => symbol to output
     * @param array $request
     */
    public function __construct(
        string $defaultSortKey,
        array $labelMap,
        array $arrowMap = ['asc' => '&darr;', 'desc' => '&uarr;', '' => ''],
        array $request = []
    ) {
        if ($request === []) {
            // Since we can't have expressions as default values in the param list...
            $request = $_GET;
        }

        $this->labelMap       = $labelMap;
        $this->currentSortKey = (!empty($request['order']) && isset($labelMap[$request['order']]))
            ? $request['order']
            : $defaultSortKey;
        $this->currentSortDir =
            (empty($request['sort']) || $request['sort'] === $this->current()['defaultSort'])
            ? $this->current()['defaultSort']
            : self::SORT_DIRS[$this->current()['defaultSort']];
        $this->arrowMap       = $arrowMap;
    }

    public function emit($outputKey) {
        $outputData = $this->getData($outputKey);
        // Fail silently if we have nothing to output
        if (!isset($outputData) || empty($outputData['text'])) {
            return '';
        }

        // Fail gracefully if we got invalid input
        if (!isset(self::SORT_DIRS[$outputData['defaultSort']], self::SORT_DIRS[$this->currentSortDir])) {
            return $outputData['text'];
        }

        $isCurrentKey  = ($outputKey === $this->currentSortKey);
        $outputSortDir = ($isCurrentKey) ? self::SORT_DIRS[$this->currentSortDir] : $outputData['defaultSort'];
        $outputArrow   = ($isCurrentKey) ? $this->arrowMap[$this->currentSortDir] : '';

        return sprintf(
            '<a href="?%s">%s</a> %s',
            Format::get_url(['page'], true, false, ['order' => $outputKey, 'sort' => $outputSortDir]),
            $outputData['text'],
            $outputArrow
        );
    }

    public function current(): ?array {
        return $this->getData($this->currentSortKey);
    }

    public function getData($sortKey): ?array {
        return $this->labelMap[$sortKey] ?? null;
    }

    public function getAllSortKeys(): array {
        return array_keys($this->labelMap);
    }

    public function getOrderBy(): ?string {
        return $this->current()['dbColumn'] ?? null;
    }

    public function getOrderDir(): string {
        return $this->currentSortDir;
    }

    public function getSortKey(): string {
        return $this->currentSortKey;
    }
}
