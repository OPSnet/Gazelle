<?php

namespace Gazelle;

class LogfileSummary {
    /** @var Logfile[] */
    protected $list;
    protected $allChecksum;
    protected $lowestScore;

    public function __construct() {
        $this->list = [];
    }

    public function add(Logfile $log) {
        $this->list[] = $log;
        $this->allChecksum = is_null($this->allChecksum)
            ? $log->checksum()
            : $this->allChecksum && $log->checksum();
        $this->lowestScore = is_null($this->lowestScore)
            ? $log->score()
            : min($this->lowestScore, $log->score());
    }

    public function checksum() {
        return $this->allChecksum;
    }

    public function checksumStatus() {
        return $this->allChecksum ? '1' : '0';
    }

    public function overallScore() {
        return is_null($this->lowestScore) ? 0 : $this->lowestScore;
    }

    public function all() {
        return $this->list;
    }

    public function count() {
        return count($this->list);
    }
}
