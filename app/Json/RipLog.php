<?php

namespace Gazelle\Json;

class RipLog extends \Gazelle\Json {
    protected $logId;
    protected $torrentId;
    protected $ripLog;

    public function __construct(int $torrentId, int $logId) {
        parent::__construct();
        $this->logId     = $logId;
        $this->torrentId = $torrentId;
        try {
            $this->ripLog = new \Gazelle\RipLog($this->torrentId, $this->logId);
        } catch (\Gazelle\Exception\ResourceNotFoundException $e) {
            throw new $e;
        }
    }

    public function payload(): array {
        $logFile = (new \Gazelle\File\RipLog)->get([$this->torrentId, $this->logId]);
        return [
            'id'                => $this->torrentId,
            'logid'             => $this->logId,
            'log'               => $logFile === false ? null : base64_encode($logFile),
            'log_sha256'        => $logFile === false ? null : hash('sha256', $logFile),
            'score'             => $this->ripLog->score(),
            'score_adjusted'    => $this->ripLog->scoreAdjusted(),
            'checksum'          => $this->ripLog->checksum(),
            'checksum_adjusted' => $this->ripLog->checksumAdjusted(),
            'checksum_state'    => $this->ripLog->checksumState(),
            'adjusted'          => $this->ripLog->adjusted(),
            'adjusted_by'       => $this->ripLog->adjustedBy(),
            'adjusted_reason'   => $this->ripLog->adjustmentReason(),
            'ripper'            => $this->ripLog->ripper(),
            'ripper_lang'       => $this->ripLog->ripperLang(),
            'ripper_version'    => $this->ripLog->ripperVersion(),
            'checker_version'   => $this->ripLog->checkerVersion(),
        ];
    }
}

