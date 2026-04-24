<?php

namespace Gazelle\Json;

use Gazelle\Torrent;

class Riplog extends \Gazelle\Json {
    public function __construct(
        protected Torrent $torrent,
        protected int $logId,
    ) {}

    public function payload(): array {
        $file = new \Gazelle\File\RipLog($this->torrent->id, $this->logId);
        if (!$file->exists()) {
            $this->failure = 'no such log';
            return [];
        }

        $riplog = new \Gazelle\Riplog($this->torrent, $this->logId);
        $file   = $riplog->file()->get();
        return [
            'id'                => $this->torrent->id,
            'logid'             => $this->logId,
            'log'               => $file === false ? null : base64_encode($file),
            'log_sha256'        => $file === false ? null : hash('sha256', $file),
            'score'             => $riplog->score(),
            'score_adjusted'    => $riplog->scoreAdjusted(),
            'checksum'          => $riplog->isChecksumOk(),
            'checksum_adjusted' => $riplog->isActualChecksumOk(),
            'checksum_state'    => $riplog->checksumState(),
            'adjusted'          => $riplog->isAdjusted(),
            'adjusted_by'       => $riplog->adjusterUserId(),
            'adjusted_reason'   => $riplog->adjustmentReason(),
            'ripper'            => $riplog->ripper(),
            'ripper_lang'       => $riplog->ripperLanguage(),
            'ripper_version'    => $riplog->ripperVersion(),
            'checker_version'   => $riplog->checkerVersion(),
            'success'           => true,
        ];
    }
}
