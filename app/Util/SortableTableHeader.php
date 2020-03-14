<?php

namespace Gazelle\Util;

class SortableTableHeader {
    /** @var array Key is sortkey, value is human-readable column name */
    private $labelMap = [];

    private $currentSortKey;

    private $currentSortDir;

    /** @var array Key is sort direction, value is symbol to output */
    private $arrowMap = ['asc' => '&uarr;', 'desc' => '&darr;'];

    public function __construct(array $labelMap, $currentSortKey, $currentSortDir, array $arrowMap = []) {
        $this->labelMap = $labelMap;
        $this->currentSortKey = $currentSortKey;
        $this->currentSortDir = $currentSortDir;
        if (!empty($arrowMap)) {
            $this->arrowMap = $arrowMap;
        }
    }

    public function emit($outputKey, $defaultSortDir) {
        // Fail silently if we have nothing to output
        if (!isset($this->labelMap[$outputKey])) {
            return '';
        }

        // Fail gracefully if we got invalid input
        if (!isset($this->arrowMap[$defaultSortDir])) {
            return $this->labelMap[$outputKey];
        }

        $sortDirs = array_keys($this->arrowMap);
        $flippedSortDir = ($this->currentSortDir === $sortDirs[0]) ? $sortDirs[1] : $sortDirs[0];
        $outputSortDir = ($outputKey === $this->currentSortKey) ? $flippedSortDir : $defaultSortDir;

        return '<a href="?'
            . \Format::get_url(['page'], true, false, ['order' => $outputKey, 'sort' => $outputSortDir])
            . '">' . $this->labelMap[$outputKey] . '</a> '
            . (($outputKey === $this->currentSortKey) ? $this->arrowMap[$this->currentSortDir] : '');
    }
}
