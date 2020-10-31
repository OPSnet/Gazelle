<?php
use Phinx\Seed\AbstractSeed;

class ClientWhitelistSeeder extends AbstractSeed {
    public function run() {
        foreach ([
            ["Deluge 1.2.x",                "-DE12"],
            ["Deluge 1.3.x",                "-DE13"],
            ["Deluge 2.x",                  "-DE2"],
            ["Flud 1.4.8",                  "-FL148"],
            ["Flud 1.4.9",                  "-FL149"],
            ["Halite 0.4.x",                "-HL04"],
            ["KTorrent 4.x",                "-KT4"],
            ["KTorrent 5.x",                "-KT5"],
            ["Libtorrent (Rasterbar)",       "-LT"],
            ["libtorrent (rtorrent) 0.11.x", "-lt0B"],
            ["libtorrent (rtorrent) 0.12.x", "-lt0C"],
            ["libtorrent (rtorrent) 0.13.x", "-lt0D"],
            ["Mainline",                     "M"],
            ["qBittorrent 2.x",              "-qB2"],
            ["qBittorrent 3.x",              "-qB3"],
            ["qBittorrent 4.0.x",            "-qB40"],
            ["qBittorrent 4.1.x",            "-qB41"],
            ["qBittorrent 4.2.x",            "-qB42"],
            ["Transmission 1.5.4",           "-TR154"],
            ["Transmission 1.7.x",           "-TR17"],
            ["Transmission 1.9.x",           "-TR19"],
            ["Transmission 2.x",             "-TR2"],
            ["Transmission 3.0.x",           "-TR30"],
            ["uTorrent 1.8.x",               "-UT18"],
            ["uTorrent 2.0.4",               "-UT204"],
            ["uTorrent 2.1.x",               "-UT21"],
            ["uTorrent 2.2.x",               "-UT22"],
            ["uTorrent 3.5.3",               "-UT353"],
            ["uTorrent 3.5.4",               "-UT354"],
            ["uTorrent 3.5.5",               "-UT355"],
            ["uTorrent Mac 1.5.x",           "-UM15"],
            ["uTorrent Mac 1.8.x",           "-UM18"],
        ] as $client) {
            $this->table('xbt_client_whitelist')->insert([
                'vstring' => $client[0],
                'peer_id' => $client[1],
            ])->save();
        }
    }
}
