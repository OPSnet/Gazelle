<?php

use Phinx\Seed\AbstractSeed;

class TorrentSeeder extends AbstractSeed {
    /**
     * To reset and rerun this seed:
SET FOREIGN_KEY_CHECKS = 0;

TRUNCATE torrents_tags;
TRUNCATE torrents_group;
TRUNCATE torrents_artists;
TRUNCATE wiki_torrents;
TRUNCATE torrents;
TRUNCATE artists_alias;
TRUNCATE artists_group;
TRUNCATE tags;

TRUNCATE login_attempts;
TRUNCATE users_sessions;
TRUNCATE users_info;
TRUNCATE users_main;
TRUNCATE users_history_emails;
TRUNCATE users_notifications_settings;

SET FOREIGN_KEY_CHECKS = 1;
     */
    const DISCOGS_MAX = 11747136;
    const TORRENTS = 10;

    private function getRandomDiscogsAlbum() {
        $id = rand(1, self::DISCOGS_MAX);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch,CURLOPT_USERAGENT,'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13');
        curl_setopt($ch, CURLOPT_URL, 'https://api.discogs.com/releases/'.$id);
        $result = curl_exec($ch);
        curl_close($ch);
        return json_decode($result);
    }

    public function run() {
        $stmt = $this->query("SELECT MIN(ID) as min_id, MAX(ID) as max_id FROM users_main");
        /** @var \PDOStatement $stmt */
        $row = $stmt->fetch();
        $lowerUserId  = $row['min_id'];
        $upperUserId = $row['max_id'];

        $bencode = new OrpheusNET\BencodeTorrent\BencodeTorrent();

        $insertData = [
            'artists_group' => [],
            'artists_alias' => [],
            'torrents_group' => [],
            'wiki_torrents' => [],
            'torrents_artists' => [],
            'tags' => [],
            'torrents_tags' => [],
            'torrents' => [],
            'torrents_leech_stats' => [],
        ];

        $artists = [];
        $groups = [];
        $tags = [];

        while (count($insertData['torrents']) <= self::TORRENTS) {
            // Avoid rate limit of 25 albums per minute
            sleep(2);
            $album = $this->getRandomDiscogsAlbum();
            if (!property_exists($album, 'year') || (!empty($album->message) && $album->message === 'Release not found.') || $album->year == 0) {
                continue;
            }
            $user_id = rand($lowerUserId, $upperUserId);
            $this->output->writeln("Found torrent ...");

            $artist = $album->artists[0];
            if (!isset($artists[$artist->name])) {
                $artists[$artist->name] = [
                    'id' => count($artists) + 1,
                    'obj' => $artist,
                ];
                $insertData['artists_group'][] = ['Name' => $artist->name];
                $insertData['artists_alias'][] = ['ArtistID' => $artists[$artist->name]['id'], 'Name' => $artist->name];
            }

            foreach ($album->genres as $idx => $genre) {
                $genre = str_replace([' ', '&'], ['.', ''], strtolower($genre));
                $album->genres[$idx] = $genre;
                if (!isset($tags[$genre])) {
                    $insertData['tags'][] = [
                        'Name' => $genre,
                        'TagType' => 'genre',
                        'Uses' => 1,
                        'UserID' => $user_id
                    ];
                    $tags[$genre] = ['id' => (count($tags) + 1), 'genre' => $genre, 'idx' => count($insertData)];
                }
            }
            if (!isset($groups[$album->title])) {
                $wikiBody = !empty($album->notes) ? $album->notes . "\n\n" : '';
                foreach ($album->tracklist as $track) {
                    $wikiBody .= "{$track->position}. {$track->title} ({$track->duration})\n";
                }
                $insertData['torrents_group'][] = [
                    'CategoryID' => 1,
                    'Name' => $album->title,
                    'Year' => $album->year,
                    'CatalogueNumber' => $album->labels[0]->catno,
                    'RecordLabel' => $album->labels[0]->name,
                    'RevisionID' => count($groups) + 1,
                    'WikiBody' => $wikiBody,
                    'WikiImage' => '',
                    'ReleaseType' => 1,
                    'VanityHouse' => 0
                ];

                $insertData['wiki_torrents'][] = [
                    'PageID' => count($groups) + 1,
                    'Body' => $wikiBody,
                    'UserID' => $user_id,
                    'Summary' => 'Uploaded new torrent',
                    'Image' => ''
                ];

                foreach ($album->genres as $genre) {
                    $insertData['torrents_tags'][] = [
                        'TagID' => $tags[$genre]['id'],
                        'GroupID' => count($groups) + 1,
                        'PositiveVotes' => 10
                    ];
                }

                $insertData['torrents_artists'][] = [
                    'GroupID' => count($groups) + 1,
                    'ArtistID' => $artists[$album->artists[0]->name]['id'],
                    'AliasID' => $artists[$album->artists[0]->name]['id'],
                    'UserID' => $user_id,
                    'Importance' => 1
                ];
                $groups[$album->title] = ['id' => count($groups) + 1, 'album' => $album];
            }

            $media = (isset($album->formats[0]) && $album->formats[0]->name === 'Vinyl') ? 'Vinyl' : 'CD';

            $torrent_id = count($insertData['torrents']) + 1;
            $files = [];
            $fileList = [];
            $delim = "\xC3\xB7"; // U+00F7 DIVISION SIGN
            foreach ($album->tracklist as $track) {
                $length = rand(1, 45573573);
                $name = "{$track->position}. {$track->title}.mp3";
                $files[] = ['length' => $length, 'path' => [$name]];
                $fileList[] = ".mp3 s{$length} {$name} {$delim}";
            }
            $fileList = implode('\n', $fileList);
            /** @noinspection PhpUnhandledExceptionInspection */
            $bencode->setData([
                'announce' => 'https://localhost:34000/hash_pass/announce',
                'comment' => "https://localhost:8080//torrents.php?torrentid={$torrent_id}",
                'creation date' => 1489617624,
                'encoding' => 'UTF-8',
                'info' => [
                    'files' => $files,
                    'name' => "{$album->artists[0]->name} - {$album->title} ({$album->year})",
                    'piece length' => 262144,
                    'pieces' => 'string of pieces'
                ]

            ]);
            $insertData['torrents'][] = [
                'GroupID' => $groups[$album->title]['id'],
                'UserID' => $user_id,
                'Media' => $media,
                'Format' => 'MP3',
                'Encoding' => '320',
                'Remastered' => 0,
                'RemasterYear' => 0,
                'RemasterTitle' => '',
                'RemasterRecordLabel' => '',
                'RemasterCatalogueNumber' => '',
                'Scene' => 0,
                'HasLog' => 0,
                'HasCue' => 0,
                'HasLogDB' => 0,
                'LogScore' => 100,
                'LogChecksum' => 1,
                'info_hash' => $bencode->getHexInfoHash(),
                'FileCount' => count($album->tracklist),
                'FileList' => $fileList,
                'FilePath' => "{$album->artists[0]->name} - {$album->title} ({$album->year})",
                'Size' => '253809759',
                'Time' => '2018-03-22 02:24:19',
                'Description' => '',
                'FreeTorrent' => 0,
                'FreeLeechType' => 0
            ];
        }

        foreach ($insertData as $table => $data) {
            $this->table($table)->insert($data)->saveData();
        }

        $this->execute('UPDATE tags SET Uses = ( SELECT COUNT(*) FROM torrents_tags WHERE torrents_tags.TagID = tags.ID GROUP BY TagID)');
        $this->execute('INSERT INTO torrents_leech_stats (TorrentID) SELECT ID FROM torrents');
    }
}
