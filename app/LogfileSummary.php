<?php

namespace Gazelle;

class LogfileSummary {
    protected $list;
    protected $allChecksum;
    protected $lowestScore;

    public function __construct() {
        $this->list = [];
    }

    public function add (Logfile $log) {
        $this->list[] = $log;
        $this->allChecksum = is_null($this->allChecksum)
            ? $this->allChecksum
            : $this->allChecksum && $log->checksum();
        $this->lowestScore = is_null($this->lowestScore)
            ? $this->lowestScore
            : min($this->lowestScore, $log->score());
    }

    public function checksumStatus () {
        return $this->allChecksum ? '1' : '0';
    }

    public function overallScore () {
        return is_null($this->lowestScore) ? 0 : $this->lowestScore;
    }

    public function all() {
        return $this->list;
    }

    public function count() {
        return count($this->list);
    }
}
