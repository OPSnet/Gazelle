<?php

namespace Gazelle\Json;

class RipLog extends \Gazelle\Json {
    public function __construct(
        protected int $torrentId,
        protected int $logId,
    ) {}

    public function payload(): array {
        try {
            $logFile = (new \Gazelle\File\RipLog)->get([$this->torrentId, $this->logId]);
            $ripLog = new \Gazelle\RipLog($this->torrentId, $this->logId);
        } catch (\Gazelle\Exception\ResourceNotFoundException) {
            return [];
        }

        return [
            'id'                => $this->torrentId,
            'logid'             => $this->logId,
            'log'               => $logFile === false ? null : base64_encode($logFile),
            'log_sha256'        => $logFile === false ? null : hash('sha256', $logFile),
            'score'             => $ripLog->score(),
            'score_adjusted'    => $ripLog->scoreAdjusted(),
            'checksum'          => $ripLog->checksum(),
            'checksum_adjusted' => $ripLog->checksumAdjusted(),
            'checksum_state'    => $ripLog->checksumState(),
            'adjusted'          => $ripLog->adjusted(),
            'adjusted_by'       => $ripLog->adjustedBy(),
            'adjusted_reason'   => $ripLog->adjustmentReason(),
            'ripper'            => $ripLog->ripper(),
            'ripper_lang'       => $ripLog->ripperLang(),
            'ripper_version'    => $ripLog->ripperVersion(),
            'checker_version'   => $ripLog->checkerVersion(),
        ];
    }
}
