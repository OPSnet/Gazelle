<?php

namespace Gazelle\Json\Stats;

class General extends \Gazelle\Json {
    public function __construct(
        protected \Gazelle\Stats\Request $reqStat  = new \Gazelle\Stats\Request(),
        protected \Gazelle\Stats\Torrent $torStat  = new \Gazelle\Stats\Torrent(),
        protected \Gazelle\Stats\Users   $userStat = new \Gazelle\Stats\Users(),
    ) {}

    public function payload(): array {
        return [
            'maxUsers'             => USER_LIMIT,
            'enabledUsers'         => $this->userStat->enabledUserTotal(),
            'usersActiveThisDay'   => $this->userStat->dayActiveTotal(),
            'usersActiveThisWeek'  => $this->userStat->weekActiveTotal(),
            'usersActiveThisMonth' => $this->userStat->monthActiveTotal(),
            'seederCount'          => $this->userStat->seederTotal(),
            'leecherCount'         => $this->userStat->leecherTotal(),
            'torrentCount'         => $this->torStat->torrentTotal(),
            'releaseCount'         => $this->torStat->albumTotal(),
            'artistCount'          => $this->torStat->artistTotal(),
            'perfectFlacCount'     => $this->torStat->perfectFlacTotal(),
            'requestCount'         => $this->reqStat->total(),
            'filledRequestCount'   => $this->reqStat->filledTotal(),
        ];
    }
}
