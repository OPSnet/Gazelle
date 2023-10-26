<?php

namespace Gazelle\Stats;

use Gazelle\Enum\UserStatus;

class Economic extends \Gazelle\Base {
    final const CACHE_KEY = 'stats_eco';

    protected array|null $info;

    public function flush(): static {
        unset($this->info);
        self::$cache->delete_value(self::CACHE_KEY);
        return $this;
    }

    public function info(): array {
        if (isset($this->info)) {
            return $this->info;
        }
        $info = self::$cache->get_value(self::CACHE_KEY);
        if ($info === false ) {
            $info = self::$db->rowAssoc("
                SELECT sum(uls.Uploaded) AS upload_total,
                    sum(uls.Downloaded)  AS download_total
                FROM users_main um
                INNER JOIN users_leech_stats AS uls ON (uls.UserID = um.ID)
                WHERE um.Enabled = ?
                ", UserStatus::enabled->value
            );

            [$info['bonus_total'], $info['bonus_stranded_total']] = self::$db->row("
                SELECT sum(ub.points),
                    sum(if(um.Enabled = ?, 0, ub.points))
                FROM user_bonus ub
                INNER JOIN users_main um ON (um.ID = ub.user_id)
                ", UserStatus::enabled->value
            );

            $info['bounty_total'] = (int)self::$db->scalar("
                SELECT SUM(Bounty) FROM requests_votes
            ");

            $info['bounty_available'] = (int)self::$db->scalar("
                SELECT SUM(rv.Bounty)
                FROM requests_votes AS rv
                INNER JOIN requests AS r ON (r.ID = rv.RequestID)
                WHERE r.FillerID = 0
            ");

            [$info['snatch_total'], $info['torrent_total']] = self::$db->row("
                SELECT sum(tls.Snatched),
                    count(*)
                FROM torrents_leech_stats tls
            ");

            $info['snatch_grand_total'] = (int)self::$db->scalar("
                SELECT count(*) FROM xbt_snatched
            ");

            [$info['peer_total'], $info['seeder_total'], $info['leecher_total']] = self::$db->row("
                SELECT count(*),
                    coalesce(sum(remaining = 0), 0),
                    coalesce(sum(remaining > 0), 0)
                FROM xbt_files_users
            ");

            [$info['token_total'], $info['token_stranded_total']] = self::$db->row("
                SELECT sum(uf.tokens),
                    sum(if(um.Enabled = ?, 0, uf.tokens))
                FROM user_flt uf
                INNER JOIN users_main um ON (um.ID = uf.user_id)
                ", UserStatus::enabled->value
            );

            [$info['user_total'], $info['user_disabled_total']] = self::$db->row("
                SELECT count(*),
                    sum(if(um.Enabled = ?, 1, 0))
                FROM users_main um
                ", UserStatus::disabled->value
            );

            $info['user_peer_total'] = (int)self::$db->scalar("
                SELECT count(distinct uid)
                FROM xbt_files_users xfu
                WHERE remaining = 0
                    AND active = 1
            ");

            $info['user_mfa_total'] = (int)self::$db->scalar("
                SELECT count(*)
                FROM users_main
                WHERE 2FA_Key IS NOT NULL AND 2FA_Key != ''
            ");
            $info = array_map('intval', $info); // some db results are stringified
            self::$cache->cache_value(self::CACHE_KEY, $info, 3600);
        }
        $this->info = $info;
        return $this->info;
    }

    public function bonusTotal(): int {
        return $this->info()['bonus_total'];
    }

    public function bonusStrandedTotal(): int {
        return $this->info()['bonus_stranded_total'];
    }

    public function bountyAvailable(): int {
        return $this->info()['bounty_available'];
    }

    public function bountyTotal(): int {
        return $this->info()['bounty_total'];
    }

    public function downloadTotal(): int {
        return $this->info()['download_total'];
    }

    public function leecherTotal(): int {
        return $this->info()['leecher_total'];
    }

    public function peerTotal(): int {
        return $this->info()['peer_total'];
    }

    public function seederTotal(): int {
        return $this->info()['seeder_total'];
    }

    public function snatchGrandTotal(): int {
        return $this->info()['snatch_grand_total'];
    }

    public function snatchTotal(): int {
        return $this->info()['snatch_total'];
    }

    public function tokenTotal(): int {
        return $this->info()['token_total'];
    }

    public function tokenStrandedTotal(): int {
        return $this->info()['token_stranded_total'];
    }

    public function torrentTotal(): int {
        return $this->info()['torrent_total'];
    }

    public function uploadTotal(): int {
        return $this->info()['upload_total'];
    }

    public function userTotal(): int {
        return $this->info()['user_total'];
    }

    public function userMfaTotal(): int {
        return $this->info()['user_mfa_total'];
    }

    public function userPeerTotal(): int {
        return $this->info()['user_peer_total'];
    }

    public function userDisabledTotal(): int {
        return $this->info()['user_disabled_total'];
    }
}
