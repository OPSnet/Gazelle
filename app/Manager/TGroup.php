<?php

namespace Gazelle\Manager;

class TGroup extends \Gazelle\BaseManager {
    final const ID_KEY                 = 'zz_tg_%d';
    protected const CACHE_KEY_FEATURED = 'featured_%d';
    protected const VOTE_SIMILAR       = 'vote_similar_albums_%d';

    final const FEATURED_AOTM     = 0;
    final const FEATURED_SHOWCASE = 1;

    protected \Gazelle\User $viewer;

    public function create(
        int     $categoryId,
        string  $name,
        string  $description,
        ?int    $year,
        ?int    $releaseType,
        ?string $recordLabel,
        ?string $catalogueNumber,
        ?string $image,
        bool    $showcase = false,
    ): \Gazelle\TGroup {
        self::$db->prepared_query("
            INSERT INTO torrents_group
                   (CategoryID, Name, WikiBody, Year, RecordLabel, CatalogueNumber, WikiImage, ReleaseType, VanityHouse)
            VALUES (?,          ?,    ?,        ?,    ?,           ?,               ?,         ?,           ?)
            ", $categoryId, $name, $description, $year, $recordLabel, $catalogueNumber, $image, $releaseType, (int)$showcase
        );
        $id = self::$db->inserted_id();
        $tgroup = $this->findById((int)$id);
        self::$cache->increment_value('stats_group_count');
        if ($tgroup->categoryName() === 'Music') {
            self::$cache->decrement('stats_album_count');
        }
        return $tgroup;
    }

    public function findById(int $tgroupId): ?\Gazelle\TGroup {
        $key = sprintf(self::ID_KEY, $tgroupId);
        $id = self::$cache->get_value($key);
        if ($id === false) {
            $id = self::$db->scalar("
                SELECT ID FROM torrents_group WHERE ID = ?
                ", $tgroupId
            );
            if (!is_null($id)) {
                self::$cache->cache_value($key, $id, 7200);
            }
        }
        if (!$id) {
            return null;
        }
        $tgroup = new \Gazelle\TGroup($id);
        if (isset($this->viewer)) {
            $tgroup->setViewer($this->viewer);
        }
        return $tgroup;
    }

    /**
     * Set the viewer context, for snatched indicators etc.
     * If this is set, and Torrent object created will have it set
     */
    public function setViewer(\Gazelle\User $viewer) {
        $this->viewer = $viewer;
        return $this;
    }

    /**
     * Map a torrenthash to a group id
     */
    public function findByTorrentInfohash(string $hash): ?\Gazelle\TGroup {
        $id = (int)self::$db->scalar("
            SELECT GroupID FROM torrents WHERE info_hash = UNHEX(?)
            ", $hash
        );
        return $this->findById($id);
    }

    public function findByArtistReleaseYear(string $artistName, string $name, int $releaseType, int $year): ?\Gazelle\TGroup {
        $id = (int)self::$db->scalar("
            SELECT tg.id
            FROM torrents_group AS tg
            INNER JOIN torrents_artists AS ta ON (ta.GroupID = tg.ID)
            INNER JOIN artists_group AS ag ON (ta.ArtistID = ag.ArtistID)
            WHERE ag.Name          = ?
                AND tg.Name        = ?
                AND tg.ReleaseType = ?
                AND tg.Year        = ?
            ", $artistName, $name, $releaseType, $year
        );
        return $this->findById($id);
    }

    public function findRandom(): ?\Gazelle\TGroup {
        return $this->findById(
            (int)self::$db->scalar("
                SELECT r1.ID
                FROM torrents_group AS r1
                INNER JOIN torrents t ON (r1.ID = t.GroupID)
                INNER JOIN torrents_leech_stats tls ON (tls.TorrentID = t.ID AND tls.Seeders >= ?),
                (SELECT rand() * max(ID) AS ID FROM torrents_group) AS r2
                WHERE r1.ID >= r2.ID
                LIMIT 1
                ", RANDOM_TORRENT_MIN_SEEDS
            )
        );
    }

    public function merge(\Gazelle\TGroup $old, \Gazelle\TGroup $new, \Gazelle\User $user, \Gazelle\Log $log): bool {
        // Votes ninjutsu. This is so annoyingly complicated.
        // 1. Get a list of everybody who voted on the old group and clear their cache keys
        self::$db->prepared_query("
            SELECT concat('voted_albums_', UserID)
            FROM users_votes
            WHERE GroupID = ?
            ", $old->id()
        );
        self::$cache->delete_multi(self::$db->collect(0, false));

        self::$db->begin_transaction();

        // 2. Update the existing votes where possible, clear out the duplicates left by key
        // conflicts, and update the torrents_votes table
        self::$db->prepared_query("
            UPDATE IGNORE users_votes SET
                GroupID = ?
            WHERE GroupID = ?
            ", $new->id(), $old->id()
        );
        self::$db->prepared_query("
            DELETE FROM users_votes WHERE GroupID = ?
            ", $old->id()
        );
        self::$db->prepared_query("
            INSERT INTO torrents_votes (GroupId, Ups, Total, Score)
            SELECT                      ?,       Ups, Total, 0
            FROM (
                SELECT
                    ifnull(sum(if(Type = 'Up', 1, 0)), 0) As Ups,
                    count(*) AS Total
                FROM users_votes
                WHERE GroupID = ?
                GROUP BY GroupID
            ) AS a
            ON DUPLICATE KEY UPDATE
                Ups = a.Ups,
                Total = a.Total
            ", $new->id(), $old->id()
        );
        if (self::$db->affected_rows()) {
            // recompute score
            self::$db->prepared_query("
                UPDATE torrents_votes SET
                    Score = IFNULL(binomial_ci(Ups, Total), 0)
                WHERE GroupID = ?
                ", $new->id()
            );
        }

        // 3. Clear the votes_pairs keys
        self::$db->prepared_query("
            SELECT concat('vote_pairs_', v2.GroupId)
            FROM users_votes AS v1
            INNER JOIN users_votes AS v2 USING (UserID)
            WHERE (v1.Type = 'Up' OR v2.Type = 'Up')
                AND (v1.GroupId     IN (?, ?))
                AND (v2.GroupId NOT IN (?, ?))
            ", $old->id(), $new->id(), $old->id(), $new->id()
        );
        self::$cache->delete_multi(self::$db->collect(0, false));

        // GroupIDs
        self::$db->prepared_query("SELECT ID FROM torrents WHERE GroupID = ?", $old->id());
        $cacheKeys = [];
        while ([$TorrentID] = self::$db->next_row()) {
            $cacheKeys[] = sprintf(\Gazelle\Torrent::CACHE_KEY, $TorrentID);
        }
        self::$cache->delete_multi($cacheKeys);
        unset($cacheKeys);

        self::$db->prepared_query("
            UPDATE torrents SET
                GroupID = ?
            WHERE GroupID = ?
            ", $new->id(), $old->id()
        );
        self::$db->prepared_query("
            UPDATE wiki_torrents SET
                PageID = ?
            WHERE PageID = ?
            ", $new->id(), $old->id()
        );

        (new \Gazelle\Manager\Bookmark)->merge($old->id(), $new->id());
        (new \Gazelle\Manager\Comment)->merge('torrents', $old->id(), $new->id());

        // Collages
        self::$db->prepared_query("
            SELECT CollageID FROM collages_torrents WHERE GroupID = ?
            ", $old->id()
        );
        $collageList = self::$db->collect(0, false);
        self::$db->prepared_query("
            UPDATE IGNORE collages_torrents SET
                GroupID = ?
            WHERE GroupID = ?
            ", $new->id(), $old->id()
        );
        self::$db->prepared_query("
            DELETE FROM collages_torrents WHERE GroupID = ?
                ", $old->id()
        );
        self::$cache->delete_multi(array_map(
            fn ($id) => sprintf(\Gazelle\Collage::CACHE_KEY, $id), $collageList
        ));

        // Requests
        self::$db->prepared_query("
            SELECT concat('request_', ID) FROM requests WHERE GroupID = ?
            ", $old->id()
        );
        self::$cache->delete_multi(self::$db->collect(0, false));
        self::$db->prepared_query("
            UPDATE requests SET
                GroupID = ?
            WHERE GroupID = ?
            ", $new->id(), $old->id()
        );

        self::$db->prepared_query("
            UPDATE group_log SET
                GroupID = ?
            WHERE GroupID = ?
            ", $new->id(), $old->id()
        );

        $old->remove($user);
        self::$db->commit();

        $new->refresh();

        self::$cache->delete_multi([
            "requests_group_" . $new->id(),
            "torrent_collages_" . $new->id(),
            "torrent_collages_personal_" . $new->id(),
            "votes_" . $new->id(),
        ]);
        return true;
    }

    protected function featuredAlbum(int $type): array {
        $key = sprintf(self::CACHE_KEY_FEATURED, $type);
        if (($featured = self::$cache->get_value($key)) === false) {
            $featured = self::$db->rowAssoc("
                SELECT fa.GroupID,
                    tg.Name,
                    tg.WikiImage,
                    fa.ThreadID,
                    fa.Title
                FROM featured_albums AS fa
                INNER JOIN torrents_group AS tg ON (tg.ID = fa.GroupID)
                WHERE Ended IS NULL AND type = ?
                ", $type
            );
            if (!is_null($featured)) {
                $featured['artist_name'] = \Artists::display_artists(\Artists::get_artist($featured['GroupID']), false, false);
                $featured['image']       = image_cache_encode($featured['WikiImage']);
            }
            self::$cache->cache_value($key, $featured, 86400 * 7);
        }
        return $featured ?? [];
    }

    public function featuredAlbumAotm(): array {
        return $this->featuredAlbum(self::FEATURED_AOTM);
    }

    public function featuredAlbumShowcase(): array {
        return $this->featuredAlbum(self::FEATURED_SHOWCASE);
    }

    /**
     * Find all the music releases that have a FLAC upload which could be used to
     * produce V0 and 320 transcodes. If there is more than one FLAC in an
     * edition it does not really matter which one is chosen. Any will be of
     * sufficient quality to generate a lossy transcode.
     */
    public function refreshBetterTranscode(): int {
        self::$db->begin_transaction();
        self::$db->prepared_query("
            DELETE FROM better_transcode_music;
        ");
        self::$db->prepared_query("
            INSERT IGNORE INTO better_transcode_music (tgroup_id, want_v0, want_320, edition)
            SELECT g.ID,
                if(mp3__v0.ID is null, 1, 0),
                if(mp3_320.ID is null, 1, 0),
                F.edition
            FROM torrents_group g
            INNER JOIN (
                SELECT DISTINCT GroupID,
                    concat_ws(char(31), Remastered, RemasterYear, RemasterTitle, RemasterRecordLabel, RemasterCatalogueNumber) AS edition
                FROM torrents
                WHERE format = 'FLAC'
            ) F ON (F.GroupID = g.ID)
            LEFT JOIN torrents mp3__v0 ON (mp3__v0.GroupID = F.GroupID and mp3__v0.Format = 'MP3' AND mp3__v0.Encoding = 'V0 (VBR)')
            LEFT JOIN torrents mp3_320 ON (mp3_320.GroupID = F.GroupID and mp3_320.Format = 'MP3' AND mp3_320.Encoding = '320')
            WHERE g.CategoryID = 1
                AND (mp3__v0.ID IS NULL OR mp3_320.ID IS NULL)
        ");
        $affected = self::$db->affected_rows();
        self::$db->commit();
        return $affected;
    }

    public function similarVote(int $tgroupId): array {
        $key = sprintf(self::VOTE_SIMILAR, $tgroupId);
        $similar = self::$cache->get_value($key);
        if ($similar === false || !isset($similar[$tgroupId])) {
            self::$db->prepared_query("
                SELECT v.GroupID
                FROM (
                    SELECT UserID
                    FROM users_votes
                    WHERE Type = 'Up' AND GroupID = ?
                ) AS a
                INNER JOIN users_votes AS v USING (UserID)
                WHERE v.GroupID != ?
                GROUP BY v.GroupID
                HAVING sum(if(v.Type = 'Up', 1, 0)) > 0
                    AND binomial_ci(sum(if(v.Type = 'Up', 1, 0)), count(*)) > 0.3
                ORDER BY binomial_ci(sum(if(v.Type = 'Up', 1, 0)), count(*)),
                    count(*) DESC
                LIMIT 10
                ", $tgroupId, $tgroupId
            );
            $similar = self::$db->collect(0, false);
            self::$cache->cache_value($key, $similar, 3600);
        }
        $list = [];
        foreach ($similar as $s) {
            $tgroup = $this->findById($s);
            if ($tgroup) {
                $list[] = $tgroup;
            }
        }
        return $list;
    }

    /**
     * Break out one torrent in the group to a new category.
     * If there are no torrents left, remove the current group.
     */
    public function changeCategory(
        int                     $categoryId,
        string                  $name,
        int                     $year,
        ?string                 $artistName,
        ?int                    $releaseType,
        \Gazelle\TGroup         $old,
        \Gazelle\Torrent        $torrent,
        \Gazelle\Manager\Artist $artistMan,
        \Gazelle\User           $user,
        \Gazelle\Log            $logger,
    ): ?\Gazelle\TGroup {
        if ($old->categoryId() === $categoryId) {
            return null;
        }
        switch ((new Category)->findNameById($categoryId)) {
            case 'Music':
                if (empty($artistName) || !$year || !$releaseType) {
                    return null;
                }
                break;

            case 'Audiobooks':
            case 'Comedy':
                $releaseType = null;
                if (!$year) {
                    return null;
                }
                break;

            case 'Applications':
            case 'Comics':
            case 'E-Books':
            case 'E-Learning Videos':
                $releaseType = null;
                $year        = null;
                break;

            default:
                return null;
        }

        $new = $this->create(
            categoryId:      $categoryId,
            description:     $old->description(),
            image:           $old->image(),
            name:            $name,
            year:            $year,
            releaseType:     $releaseType,
            recordLabel:     null,
            catalogueNumber: null,
        );
        if ($new->categoryName() === 'Music') {
            $new->addArtists([ARTIST_MAIN], [$artistName], $user, $artistMan, $logger);
        }
        $torrent->setField('GroupID', $new->id())->modify();

        // Refresh the old group, otherwise remove it if there is nothing left
        if (self::$db->scalar('SELECT ID FROM torrents WHERE GroupID = ?', $old->id())) {
            $old->flush()->refresh();
        } else {
            // TODO: votes etc.
            (new \Gazelle\Manager\Bookmark)->merge($old->id(), $new->id());
            (new \Gazelle\Manager\Comment)->merge('torrents', $old->id(), $new->id());
            $logger->merge($old->id(), $new->id());
            $old->remove($user);
        }
        $new->refresh();

        $logger->group($new->id(), $user->id(),
            "category changed from {$old->categoryId()} to {$new->categoryId()}, merged from group {$old->id()}"
            )
            ->general("Torrent {$torrent->id()} was changed to category {$new->categoryId()} by {$user->label()}");
        return $new;
    }
}
