<?php

use Phinx\Seed\AbstractSeed;

class TorrentSeeder extends AbstractSeed {
    /**
     * To reset and rerun this seed:
SET FOREIGN_KEY_CHECKS = 0;

TRUNCATE torrents_files;
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
    const TORRENTS = 20;

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
        $stmt = $this->query("SELECT COUNT(*) AS count FROM users_main");
        $user_count  = (int) $stmt->fetch()['count'];

        $bencode = new OrpheusNET\BencodeTorrent\BencodeTorrent();

        $insert_data = [
            'artists_group' => [],
            'artists_alias' => [],
            'torrents_group' => [],
            'wiki_torrents' => [],
            'torrents_artists' => [],
            'tags' => [],
            'torrents_tags' => [],
            'torrents' => [],
            'torrents_files' => []
        ];

        $artists = [];
        $groups = [];
        $tags = [
            'rock' => ['id' => 1, 'genre' => 'rock'],
            'pop' => ['id' => 2, 'genre' => 'pop'],
            'female.fronted.symphonic.death.metal' => ['id' => 3, 'genre' => 'female.fronted.symphonic.death.metal']
        ];

        $i = 0;
        while ($i < self::TORRENTS) {
            // Avoid rate limit of 25 albums per minute
            sleep(2);
            $album = $this->getRandomDiscogsAlbum();
            if (!property_exists($album, 'year') || (!empty($album->message) && $album->message === 'Release not found.') || $album->year == 0) {
                continue;
            }
            $user_id = rand(1, $user_count);
            $this->output->writeln("Found torrent {$i}...");

            $artist = $album->artists[0];
            if (!isset($artists[$artist->name])) {
                $artists[$artist->name] = [
                    'id' => count($artists) + 1,
                    'obj' => $artist,
                ];
                $insert_data['artists_group'][] = ['Name' => $artist->name];
                $insert_data['artists_alias'][] = ['ArtistID' => $artists[$artist->name]['id'], 'Name' => $artist->name];
            }

            foreach ($album->genres as $idx => $genre) {
                $genre = str_replace([' ', '&'], ['.', ''], strtolower($genre));
                $album->genres[$idx] = $genre;
                if (!isset($tags[$genre])) {
                    $insert_data['tags'][] = [
                        'Name' => $genre,
                        'TagType' => 'genre',
                        'Uses' => 1,
                        'UserID' => $user_id
                    ];
                    $tags[$genre] = ['id' => (count($tags) + 1), 'genre' => $genre, 'idx' => count($insert_data)];
                }
            }
            if (!isset($groups[$album->title])) {
                $wiki_body = !empty($album->notes) ? $album->notes . "\n\n" : '';
                foreach ($album->tracklist as $track) {
                    $wiki_body .= "{$track->position}. {$track->title} ({$track->duration})\n";
                }
                $insert_data['torrents_group'][] = [
                    'CategoryID' => 1,
                    'Name' => $album->title,
                    'Year' => $album->year,
                    'CatalogueNumber' => $album->labels[0]->catno,
                    'RecordLabel' => $album->labels[0]->name,
                    'Time' => '2018-03-22 02:24:19',
                    'RevisionID' => count($groups) + 1,
                    'WikiBody' => $wiki_body,
                    'WikiImage' => '',
                    'ReleaseType' => 1,
                    'VanityHouse' => 0
                ];

                $insert_data['wiki_torrents'][] = [
                    'PageID' => count($groups) + 1,
                    'Body' => $wiki_body,
                    'UserID' => $user_id,
                    'Summary' => 'Uploaded new torrent',
                    'Time' => '2018-03-22 02:24:19',
                    'Image' => ''
                ];

                foreach ($album->genres as $genre) {
                    $insert_data['torrents_tags'][] = [
                        'TagID' => $tags[$genre]['id'],
                        'GroupID' => count($groups) + 1,
                        'PositiveVotes' => 10
                    ];
                }

                $insert_data['torrents_artists'][] = [
                    'GroupID' => count($groups) + 1,
                    'ArtistID' => $artists[$album->artists[0]->name]['id'],
                    'AliasID' => $artists[$album->artists[0]->name]['id'],
                    'UserID' => $user_id,
                    'Importance' => 1
                ];
                $groups[$album->title] = ['id' => count($groups) + 1, 'album' => $album];
            }

            $media = ($album->formats[0]->name === 'Vinyl') ? 'Vinyl' : 'CD';

            $torrent_id = count($insert_data['torrents']) + 1;
            $files = [];
            $file_list = [];
            $delim = utf8_encode(chr(0xF7));
            foreach ($album->tracklist as $track) {
                $length = rand(1, 45573573);
                $name = "{$track->position}. {$track->title}.mp3";
                $files[] = ['length' => $length, 'path' => [$name]];
                $file_list[] = ".mp3 s{$length} {$name} {$delim}";
            }
            $file_list = implode('\n', $file_list);
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
            $insert_data['torrents'][] = [
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
                'FileList' => $file_list,
                'FilePath' => "{$album->artists[0]->name} - {$album->title} ({$album->year})",
                'Size' => '253809759',
                'Time' => '2018-03-22 02:24:19',
                'Description' => '',
                'FreeTorrent' => 0,
                'FreeLeechType' => 0
            ];

            $insert_data['torrents_files'][] = [
                'TorrentID' => count($insert_data['torrents']) + 1,
                'File' => b'd8:announce65:https://localhost:34000/4f9587fbcb06fe09165e4f84d35d0403/announce7:comment53:https://localhost:8080//torrents.php?id=1&torrentid=113:creation datei1489617624e8:encoding5:UTF-84:infod5:filesld6:lengthi45573573e4:pathl19:01 - Nightmare.flaceed6:lengthi31675140e4:pathl31:02 - Welcome to the Family.flaceed6:lengthi36911187e4:pathl21:03 - Danger Line.flaceed6:lengthi47893264e4:pathl22:04 - Buried Alive.flaceed6:lengthi40839480e4:pathl29:05 - Natural Born Killer.flaceed6:lengthi40787176e4:pathl21:06 - So Far Away.flaceed6:lengthi34999093e4:pathl22:07 - God Hates Us.flaceed6:lengthi52152693e4:pathl16:08 - Victim.flaceed6:lengthi34464081e4:pathl32:09 - Tonight the World Dies.flaceed6:lengthi34485391e4:pathl17:10 - Fiction.flaceed6:lengthi81953099e4:pathl17:11 - Save Me.flaceed6:lengthi1100e4:pathl33:Avenged Sevenfold - Nightmare.cueeed6:lengthi12890e4:pathl33:Avenged Sevenfold - Nightmare.logeed6:lengthi66260e4:pathl10:folder.jpgeee4:name73:Avenged Sevenfold \xe2\x80\x8e- Nightmare (2010) [CD - FLAC - Lossless] {524026-2}12:piece lengthi262144e6:pieces19:fake torrent pieces7:privatei1e6:source3:APLee'
            ];
            $i++;
        }

        foreach ($insert_data as $table => $data) {
            $this->table($table)->insert($data)->saveData();
        }

        $this->execute('UPDATE tags SET Uses = ( SELECT COUNT(*) FROM torrents_tags WHERE torrents_tags.TagID = tags.ID GROUP BY TagID)');
    }
}
