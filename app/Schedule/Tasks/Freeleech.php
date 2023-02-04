<?php

namespace Gazelle\Schedule\Tasks;

class Freeleech extends \Gazelle\Schedule\Task
{
    public function run()
    {
        //We use this to control 6 hour freeleeches.
        // They're actually 7 hours, but don't tell anyone.
        $qId = self::$db->prepared_query("
            SELECT DISTINCT GroupID
            FROM torrents
            WHERE FreeTorrent = '1'
                AND FreeLeechType = '3'
                AND Time < now() - INTERVAL 7 HOUR");

        self::$db->prepared_query("
            UPDATE torrents
            SET FreeTorrent = '0',
                FreeLeechType = '0'
            WHERE FreeTorrent = '1'
                AND FreeLeechType = '3'
                AND Time < now() - INTERVAL 7 HOUR");

        self::$db->set_query_id($qId);
        while ([$groupID] = self::$db->next_record()) {
            self::$cache->delete_value("torrents_details_$groupID");
            self::$cache->delete_value("torrent_group_$groupID");
        }
    }
}
