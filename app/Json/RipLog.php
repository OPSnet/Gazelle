<?php

namespace Gazelle\Json;

class RipLog extends \Gazelle\Json {
    public function __construct(
        protected int $torrentId,
        protected int $logId,
    ) {}

    public function payload(): array {
        $filer = new \Gazelle\File\RipLog();
        if (!$filer->exists([$this->torrentId, $this->logId])) {
            return [
                'id'                => $this->torrentId,
                'logid'             => $this->logId,
                'log'               => false,
                'log_sha256'        => false,
                'score'             => false,
                'score_adjusted'    => false,
                'checksum'          => false,
                'checksum_adjusted' => false,
                'checksum_state'    => false,
                'adjusted'          => false,
                'adjusted_by'       => false,
                'adjusted_reason'   => false,
                'ripper'            => false,
                'ripper_lang'       => false,
                'ripper_version'    => false,
                'checker_version'   => false,
                'success'           => false,
            ];
        }

        $logFile = $filer->get([$this->torrentId, $this->logId]);
        $ripLog  = new \Gazelle\RipLog($this->torrentId, $this->logId);
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
            'success'           => true,
        ];
    }
}
