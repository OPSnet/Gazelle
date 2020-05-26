<?php

namespace Gazelle\Torrent;

class Log extends \Gazelle\Base {

    protected $id; // id of the torrent

    public function __construct ($id) {
        parent::__construct();
        $this->id = $id;
    }

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
    public function logDetails() {
        $this->db->prepared_query("
            SELECT LogID,
                coalesce(Details, '') as Details,
                Adjusted,
                AdjustedBy,
                AdjustmentReason,
                AdjustmentDetails,
                Score,
                AdjustedScore,
                `Checksum`,
                AdjustedChecksum,
                Log
            FROM torrents_logs
            WHERE TorrentID = ?
            ", $this->id
        );
        $logs = $this->db->to_array('LogID', MYSQLI_ASSOC, false);
        $details = [];
        foreach ($logs as $log) {
            $details[$log['LogID']] = [
                'adjustment' => $log['Adjusted'] === '0'
                    ? []
                    : [
                        'userId'   => $log['AdjustedBy'],
                        'score'    => $log['Score'],
                        'adjusted' => $log['AdjustedScore'],
                        'reason'   => empty($log['AdjustmentReason']) ? 'none supplied' : $log['AdjustmentReason'],
                    ],
                'log'    => $log['Log'],
                'status' => array_merge(explode("\n", $log['Details']), unserialize($log['AdjustmentDetails']) ?: []),
            ];
            if (($log['Adjusted'] === '0' && $log['Checksum'] === '0') || ($log['Adjusted'] === '1' && $log['AdjustedChecksum'] === '0')) {
                $details[$log['LogID']]['status'][] = 'Bad/No Checksum(s)';
            }
        }
        return $details;
    }
}
