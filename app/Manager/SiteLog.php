<?php

namespace Gazelle\Manager;

class SiteLog extends \Gazelle\Base {
    protected $debug;
    protected $logQuery;
    protected $totalMatches;
    protected $queryStatus;
    protected $queryError;
    protected $qid;
    protected $usernames;
    protected $userMan;

    public function __construct (\Gazelle\Debug $debug) {
        parent::__construct();
        $this->debug = $debug;
        $this->usernames = [];
        $this->userMan = new \Gazelle\Manager\User;
    }

    public function totalMatches() { return $this->totalMatches; }
    public function error()        { return $this->queryStatus; }
    public function errorMessage() { return $this->queryError; }

    public function next() {
        $this->db->set_query_id($this->qid);
        while ($result = $this->db->next_record(MYSQLI_NUM, false)) {
            [$color, $message] = $this->colorize($result[1]);
            yield [
                'id'      => $result[0],
                'time'    => $result[2],
                'color'   => $color,
                'message' => $message,
            ];
            $this->db->set_query_id($this->qid);
        }
    }

    public function load(int $page, int $offset, string $searchTerm) {
        if ($searchTerm === '') {
            $this->logQuery = $this->db->prepared_query("
                SELECT ID, Message, Time
                FROM log
                ORDER BY ID DESC
                LIMIT ?, ?
                ", $offset, LOG_ENTRIES_PER_PAGE
            );
            $this->totalMatches = $this->db->record_count();
            if ($this->totalMatches == LOG_ENTRIES_PER_PAGE) {
                $sq = new \SphinxqlQuery();
                $result = $sq->select('id')->from('log, log_delta')->limit(0, 1, 1)->sphinxquery();
                $this->debug->log_var($result, '$result');
                $this->totalMatches = min(SPHINX_MAX_MATCHES, $result->get_meta('total_found'));
            } else {
                $this->totalMatches += $offset;
            }
            $this->queryStatus = 0;
        } else {
            $page = min(SPHINX_MAX_MATCHES / TORRENTS_PER_PAGE, $page);
            $sq = new \SphinxqlQuery();
            $sq->select('id')
                ->from('log, log_delta')
                ->order_by('id', 'DESC')
                ->limit($offset, LOG_ENTRIES_PER_PAGE, $offset + LOG_ENTRIES_PER_PAGE);
            foreach (explode(' ', $searchTerm) as $s) {
                $sq->where_match($s, 'message');
            }
            $result = $sq->sphinxquery();
            $this->debug->log_var($result, '$result');
            $this->debug->set_flag('Finished SphQL query');
            if ($this->queryStatus = $result->Errno) {
                $this->queryError = $result->Error;
                $this->logQuery = $this->db->prepared_query('SET @nothing = 0');
            } else  {
                $this->totalMatches = min(SPHINX_MAX_MATCHES, $result->get_meta('total_found'));
                $logIds = $result->collect('id') ?: [0];
                $this->logQuery = $this->db->prepared_query("
                    SELECT ID, Message, Time
                    FROM log
                    WHERE ID IN (" . placeholders($logIds) . ")
                    ORDER BY ID DESC
                    ", ...$logIds
                );
            }
        }
        $this->qid = $this->db->get_query_id();
    }

    public function colorize(string $logMessage) {
        $messageParts = explode(' ', $logMessage);
        $message = '';
        $color = $colon = false;
        for ($i = 0, $n = count($messageParts); $i < $n; $i++) {
            if (strpos($messageParts[$i], SITE_URL) === 0) {
                $offset = strlen(SITE_URL) + 1; // trailing slash
                $messageParts[$i] = '<a href="'.substr($messageParts[$i], $offset).'">'.substr($messageParts[$i], $offset).'</a>';
            }
            switch ($messageParts[$i]) {
                case 'Torrent':
                case 'torrent':
                    $TorrentID = $messageParts[$i + 1];
                    if ((int)$TorrentID) {
                        $message .= ' ' . $messageParts[$i++] . " <a href=\"torrents.php?torrentid=$TorrentID\">$TorrentID</a>";
                    } else {
                        $message .= ' ' . $messageParts[$i];
                    }
                    break;
                case 'Request':
                    $RequestID = $messageParts[$i + 1];
                    if ((int)$RequestID) {
                        $message .= ' ' .$messageParts[$i++]." <a href=\"requests.php?action=view&amp;id=$RequestID\">$RequestID</a>";
                    } else {
                        $message .= ' ' .$messageParts[$i];
                    }
                    break;
                case 'Artist':
                case 'artist':
                    $ArtistID = $messageParts[$i + 1];
                    if ((int)$ArtistID) {
                        $message .= ' ' .$messageParts[$i++]." <a href=\"artist.php?id=$ArtistID\">$ArtistID</a>";
                    } else {
                        $message .= ' ' .$messageParts[$i];
                    }
                    break;
                case 'Group':
                case 'group':
                    $GroupID = $messageParts[$i + 1];
                    if ((int)$GroupID) {
                        $message .= ' ' .$messageParts[$i]." <a href=\"torrents.php?id=$GroupID\">$GroupID</a>";
                    } else {
                        $message .= ' ' .$messageParts[$i];
                    }
                    $i++;
                    break;
                case 'by':
                    $userId = 0;
                    $user = '';
                    $URL = '';
                    if ($messageParts[$i + 1] == 'user') {
                        $i++;
                        if ((int)($messageParts[$i + 1])) {
                            $userId = $messageParts[++$i];
                        }
                        $URL = "user $userId (<a href=\"user.php?id=$userId\">".substr($messageParts[++$i], 1, -1).'</a>)';
                    } elseif (in_array($messageParts[$i - 1], ['deleted', 'uploaded', 'edited', 'created', 'recovered'])) {
                        $username = $messageParts[++$i];
                        if (substr($username, -1) == ':') {
                            $username = substr($username, 0, -1);
                            $colon = true;
                        }
                        $userId = $this->usernameLookup($username);
                        $URL = $userId ? "<a href=\"user.php?id=$userId\">$username</a>".($colon ? ':' : '') : $username;
                    }
                    $message .= " by $URL";
                    break;
                case 'uploaded':
                    if ($color === false) {
                        $color = 'forestgreen';
                    }
                    $message .= ' ' .$messageParts[$i];
                    break;
                case 'deleted':
                    if ($color === false || $color === 'forestgreen') {
                        $color = 'crimson';
                    }
                    $message .= ' ' .$messageParts[$i];
                    break;
                case 'edited':
                    if ($color === false) {
                        $color = 'royalblue';
                    }
                    $message .= ' ' .$messageParts[$i];
                    break;
                case 'un-filled':
                    if ($color === false) {
                        $color = '';
                    }
                    $message .= ' ' .$messageParts[$i];
                    break;
                case 'marked':
                    if ($i == 1) {
                        $username = $messageParts[$i - 1];
                        $userId = $this->usernameLookup($username);
                        $URL = $userId ? "<a href=\"user.php?id=$userId\">$username</a>" : $username;
                        $message = $URL." ".$messageParts[$i];
                    } else {
                        $message .= ' ' .$messageParts[$i];
                    }
                    break;
                case 'Collage':
                    $CollageID = $messageParts[$i + 1];
                    if (is_numeric($CollageID)) {
                        $message .= ' ' .$messageParts[$i]." <a href=\"collages.php?id=$CollageID\">$CollageID</a>";
                        $i++;
                    } else {
                        $message .= ' ' .$messageParts[$i];
                    }
                    break;
                default:
                    $message .= ' ' .$messageParts[$i];
            }
        }
        return [$color, $message];
    }

    protected function usernameLookup(string $username) {
        if (!isset($this->usernames[$username])) {
            $user = $this->userMan->findByUsername($username);
            $this->usernames[$username] = $user ? $user->id() : false;
        }
        return $this->usernames[$username];
    }
}
