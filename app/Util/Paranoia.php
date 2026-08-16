<?php

namespace Gazelle\Util;

class Paranoia {
    /**
     * Reduce a set of "p_*" paranoia checkbox flags into a consistent list of
     * paranoia settings, as stored on Gazelle\User.
     */
    public static function calculate(array $paranoia): array {
        if (array_is_list($paranoia)) {
            $paranoia = array_fill_keys($paranoia, true);
        }

        // Reduce the user's input paranoia until it becomes consistent
        if (isset($paranoia['p_l_uniquegroups'])) {
            $paranoia['p_l_uploads'] = true;
            $paranoia['p_c_uploads'] = true;
        }

        if (isset($paranoia['p_l_uploads'])) {
            $paranoia['p_l_uniquegroups'] = true;
            $paranoia['p_l_perfectflacs'] = true;
            $paranoia['p_c_uniquegroups'] = true;
            $paranoia['p_c_perfectflacs'] = true;
            $paranoia['p_artistsadded']   = true;
        }

        if (isset($paranoia['p_collagecontribs'])) {
            $paranoia['p_l_collages'] = true;
            $paranoia['p_c_collages'] = true;
        }

        if (isset($paranoia['p_c_snatched'], $paranoia['p_c_seeding'], $paranoia['p_downloaded'])) {
            $paranoia['p_requiredratio'] = true;
        }

        // if showing exactly 2 of stats, show all 3 of stats
        $statsShown = 0;
        $stats = ['downloaded', 'uploaded', 'ratio'];
        foreach ($stats as $s) {
            if (isset($paranoia["p_$s"])) {
                $statsShown++;
            }
        }
        if ($statsShown == 2) {
            foreach ($stats as $s) {
                $paranoia["p_$s"] = true;
            }
        }

        $result = [];
        foreach (
            [
                'downloaded', 'uploaded', 'ratio', 'bonuspoints', 'lastseen', 'requiredratio', 'invitedcount', 'artistsadded', 'notifications'
            ] as $c
        ) {
            if (!isset($paranoia["p_$c"])) {
                $result[] = $c;
            }
        }
        foreach (
            [
                'torrentcomments', 'collages', 'collagecontribs', 'uploads', 'uniquegroups', 'perfectflacs', 'seeding', 'leeching', 'snatched'
            ] as $c
        ) {
            if (!isset($paranoia["p_l_$c"])) {
                $result[] = isset($paranoia["p_c_$c"]) ? $c : "$c+";
            }
        }
        foreach (['requestsfilled', 'requestsvoted'] as $bounty) {
            if (isset($paranoia["p_list_$bounty"])) {
                $paranoia["p_count_$bounty"]  = true;
                $paranoia["p_bounty_$bounty"] = true;
            }
            foreach (['list', 'count', 'bounty'] as $item) {
                if (!isset($paranoia["p_{$item}_{$bounty}"])) {
                    $result[] = "{$bounty}_{$item}";
                }
            }
        }
        if (!isset($paranoia['p_donor_heart'])) {
            $result[] = 'hide_donor_heart';
        }
        return $result;
    }
}
