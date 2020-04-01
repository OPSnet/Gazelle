<?php

namespace Gazelle\Util;

class SortableTableHeader {
    const SORT_DIRS = ['asc' => 'desc', 'desc' => 'asc'];

    /** @var array sortkey => human-readable column name */
    private $labelMap = [];

    /** @var string */
    private $currentSortKey;

    /** @var string */
    private $currentSortDir;

    /** @var array sort direction => symbol to output */
    private $arrowMap = ['asc' => '&darr;', 'desc' => '&uarr;'];

    public function __construct(array $labelMap, $currentSortKey, $currentSortDir, array $arrowMap = []) {
        $this->labelMap       = $labelMap;
        $this->currentSortKey = $currentSortKey;
        $this->currentSortDir = $currentSortDir;
        if (!empty($arrowMap)) {
            $this->arrowMap   = $arrowMap;
        }
    }

    public function emit($outputKey, $defaultSortDir) {
        // Fail silently if we have nothing to output
        if (!isset($this->labelMap[$outputKey])) {
            return '';
        }

        // Fail gracefully if we got invalid input
        if (!isset(self::SORT_DIRS[$defaultSortDir])
            || !isset(self::SORT_DIRS[$this->currentSortDir])
        ) {
            return $this->labelMap[$outputKey];
        }

        $isCurrentKey  = ($outputKey === $this->currentSortKey);
        $outputSortDir = $isCurrentKey ? self::SORT_DIRS[$this->currentSortDir] : $defaultSortDir;
        $outputArrow   = $isCurrentKey ? $this->arrowMap[$this->currentSortDir] : '';

        return sprintf(
            '<a href="?%s">%s</a> %s',
            \Format::get_url(['page'], true, false, ['order' => $outputKey, 'sort' => $outputSortDir]),
            $this->labelMap[$outputKey],
            $outputArrow
        );
    }
}
