<?php


use Phinx\Seed\AbstractSeed;

class TagSeeder extends AbstractSeed {
    public function run() {
        foreach ([
            'acoustic', 'alternative', 'alternative.rock', 'ambient', 'americana',
            'avant.garde', 'blues', 'blues.rock', 'breakbeat', 'breaks',
            'classic.rock', 'classical', 'country', 'dance', 'dark.ambient',
            'deep.house', 'disco', 'doom.metal', 'drum.and.bass', 'electro',
            'electronic', 'experimental', 'folk', 'folk.rock', 'free.jazz', 'funk',
            'garage.rock', 'hard.rock', 'hardcore', 'hip.hop', 'house', 'idm',
            'indie', 'indie.pop', 'indie.rock', 'industrial', 'instrumental',
            'jam.band', 'jazz', 'lo.fi', 'metal', 'minimal', 'new.age', 'new.wave',
            'noise', 'pop', 'pop.rock', 'post.punk', 'post.rock',
            'progressive.house', 'progressive.rock', 'psychedelic', 'punk',
            'punk.rock', 'reggae', 'rhythm.and.blues', 'rock', 'rock.and.roll',
            'shoegaze', 'singer.songwriter', 'soul', 'soundtrack', 'stoner.rock',
            'synth.pop', 'tech.house', 'techno', 'trance', 'trip.hop', 'video.game',
            'world.music'
        ] as $tag) {
            $this->table('tags')->insert([
                'Name' => $tag,
                'TagType' => 'genre',
                'UserID' => 1,
            ])->save();
        }

        foreach ([
            'abstract', 'acid', 'black.metal', 'bluegrass', 'contemporary.jazz',
            'death.metal', 'downtempo', 'drone', 'dub', 'dubstep',
            'female.vocalist', 'free.improvisation', 'french', 'fusion',
            'heavy.metal', 'japanese', 'latin', 'modern.classical', 'post.hardcore',
            'progressive.metal', 'psychedelic.rock', 'score', 'vocal',
        ] as $tag) {
            $this->table('tags')->insert([
                'Name' => $tag,
                'TagType' => 'other',
                'UserID' => 1,
            ])->save();
        }

    }
}
