<?php

namespace Gazelle\Torrent;

use Gazelle\File\RipLogHTML;
use Gazelle\Manager\User;

class Log extends \Gazelle\Base {
    public function __construct(
        public readonly int $id,
    ) {}

    /**
     * Get the summary of the logfiles associated with a torrent
     * @return array An associated array keyed by LogID of the logfile
     *    The array contains two keys, 'adjustment' and 'status'.
     *    The 'adjustment' key points to an array with the following keys:
     *      - userId (staff userid who made the last adjustment)
     *      - score (the original score of the torrent)
     *      - adjusted (adjusted score)
     *      - reason (reason given by the adjuster for adjusting the log)
     *    The 'status' key points to an unserialized array of AdjustmentDetails
     */
    public function logDetails(User $userMan = new User()): array {
        self::$db->prepared_query("
            SELECT LogID,
                Adjusted,
                Adjusted = '1' AS is_adjusted,
                AdjustedBy,
                AdjustmentReason,
                coalesce(AdjustmentDetails, 'a:0:{}') AS AdjustmentDetails,
                Score,
                AdjustedScore,
                `Checksum`,
                AdjustedChecksum,
                coalesce(Details, '') as Details
            FROM torrents_logs
            WHERE TorrentID = ?
            ", $this->id
        );
        $logs = self::$db->to_array('LogID', MYSQLI_ASSOC);
        $details = [];
        foreach ($logs as $log) {
            $logDetails = [
                'adjustment' => !$log['is_adjusted']
                    ? []
                    : [
                        'user'     => $userMan->findById($log['AdjustedBy']),
                        'score'    => $log['Score'],
                        'adjusted' => $log['AdjustedScore'],
                        'reason'   => empty($log['AdjustmentReason']) ? 'none supplied' : $log['AdjustmentReason'],
                    ],
                'html_log' => new RipLogHTML($this->id, $log['LogID'])->get(),
                'status'   => array_merge(explode("\n", $log['Details']), unserialize($log['AdjustmentDetails'])),
            ];
            if (($log['Adjusted'] === '0' && $log['Checksum'] === '0') || ($log['Adjusted'] === '1' && $log['AdjustedChecksum'] === '0')) {
                $logDetails['status'][] = 'Bad/No Checksum(s)';
            }
            $details[$log['LogID']] = $logDetails;
        }
        return $details;
    }
}
